<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.maxiblog.fr
 * @since      1.0.0
 *
 * @package    Rcp_Vat
 * @subpackage Rcp_Vat/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package Rcp_Vat
 * @subpackage Rcp_Vat/admin
 * @author Termel <admin@termel.fr>
 */
$vat_libs_path = dirname(__DIR__) . '/libs/vendor/autoload.php';
$vat_libs_path = realpath($vat_libs_path);
require_once $vat_libs_path;

class Rcp_Vat_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since 1.0.0
     * @access private
     * @var string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since 1.0.0
     * @access private
     * @var string $version The current version of this plugin.
     */
    private $version;

    private $use_wc_billing_fields = true;

    private static $rateTypes = array(
        'super_reduced' => 'Super-reduced rate',
        'reduced' => 'Reduced',
        'reduced1' => 'Reduced rate 1',
        'reduced2' => 'Reduced rate 2',
        'standard' => 'Standard rate',
        'parking' => 'Parking rate'
    );

    private $stripeTaxPercentField = "tax_percent";

    private static $defaultRateType = 'standard';

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $plugin_name
     *            The name of this plugin.
     * @param string $version
     *            The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // add admin new settings
        add_action('rcp_payments_settings', array(
            $this,
            'settings_fields'
        ));
        add_action('rcp_view_member_after', array(
            $this,
            'member_details'
        ));
        
        // add registration and profile necessary fields
        add_action('rcp_after_password_registration_field', array(
            $this,
            'rcp_vat_rcp_add_user_fields'
        ), 10, 2);
        add_action('rcp_profile_editor_after', array(
            $this,
            'rcp_vat_rcp_add_user_fields'
        ), 10, 2);
        
        // add field for admin to edit
        add_action('rcp_edit_member_after', array(
            $this,
            'rcp_vat_rcp_add_member_edit_fields'
        ));
        
        add_action('rcp_form_processing', array(
            $this,
            'rcp_vat_save_user_fields_on_registration'
        ), 10, 2);
        
        add_action('rcp_user_profile_updated', array(
            $this,
            'rcp_vat_save_user_fields_on_profile_save'
        ), 10);
        add_action('rcp_edit_member', array(
            $this,
            'rcp_vat_save_user_fields_on_profile_save'
        ), 10);
        // validation
        
        add_action('rcp_form_errors', array(
            $this,
            'rcp_vat_rcp_validate_user_fields_on_register'
        ), 10);
        add_action('rcp_edit_profile_form_errors', array(
            $this,
            'rcp_vat_rcp_validate_user_fields_on_profile_update'
        ), 10, 2);
        
        add_filter("rcp_subscription_data", array(
            $this,
            "modify_rcp_subscription_data"
        ));
        
        add_filter("rcp_stripe_create_subscription_args", array(
            $this,
            'modify_rcp_stripe_create_subscription_args'
        ), 10, 2);
        
        add_filter('rcp_stripe_customer_create_args', array(
            $this,
            'modify_rcp_stripe_customer_create_args'
        ), 10, 2);
        
        add_filter('rcp_registration_total', array(
            $this,
            'modify_total_with_VAT'
        ));
        
        add_filter('rcp_registration_recurring_total', array(
            $this,
            'modify_total_with_VAT'
        ));              
        
        add_filter('rcp_template_stack', array(
            $this,
            'add_templates_location'
        ), 10, 2);
        add_action('wp_ajax_rcp_vat_get_vat_rate', array(
            $this,
            'rcp_vat_get_vat_rate'
        ));
        
        add_action('wp_ajax_nopriv_rcp_vat_get_vat_rate', array(
            $this,
            'rcp_vat_get_vat_rate'
        ));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since 1.0.0
     */
    public function enqueue_styles()
    {
         wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rcp-vat-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since 1.0.0
     */
    public function enqueue_scripts()
    {     
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rcp-vat-admin.js', array(
            'jquery'
        ), $this->version, false);
    }
    /**
     * Add new template location for RCP to look at
     */
    function add_templates_location($template_stack, $template_names)
    {
        $localRCPPath = "rcp/";
        $dir = plugin_dir_path(__DIR__);
        
        $newLocation = realpath($dir . $localRCPPath);
        rcp_vat_log('add_templates_location:: ' . $newLocation);
        $template_stack[] = $newLocation;
        
        return $template_stack;
    }
    /**
     * Get total = tax amount + base price, given a price and a tax rate
     */
    function getTotalPrice($input_price, $vat_rate)
    {
        $result = $input_price + $this->getVATAmount($input_price, $vat_rate);
        return $result;
    }
    /**
     * Get tax amount, given a price
     */
    function getVATAmount($input_price, $vat_rate)
    {
        return $input_price * $vat_rate / 100;
    }
    /**
     * Ajax : Get rate for given country to update display
     */
    function rcp_vat_get_vat_rate()
    {
        global $rcp_options, $rcp_levels_db;
        $validator = new DvK\Vat\Validator();
        rcp_vat_log("rcp_vat_get_vat_rate ");
        rcp_vat_log($_POST);
        
        $country_code = sanitize_text_field(isset($_POST['country_code']) ? $_POST['country_code'] : '');
        $vat_business_number = sanitize_text_field(isset($_POST['rcp_company_vat_number']) ? $_POST['rcp_company_vat_number'] : '');
        $levelNb = sanitize_text_field($_POST['level']);
        if (empty($country_code)) {
            $country_code = $this->getUserCountry(get_current_user_id());
        }
        
        $country_name = $this->getCountryFromCode($country_code);
        $country_vat_rate = $this->getRateForCountry($country_code);
        rcp_vat_log("$country_name $country_vat_rate");
        $level = $rcp_levels_db->get_level($levelNb);
        
        $level_price = $level->price;
        
        $vat_amount = $this->getVATAmount($level_price, $country_vat_rate);
        $vat_label = __('VAT') . ' ' . '(' . $country_name . ' - ' . $country_vat_rate . ' %' . ')';
        
        rcp_vat_log($level);
        
        if ($vat_business_number) {
            
            //$companyVatNumber = trim($_POST['rcp_company_vat_number']);
            rcp_vat_log("VAT BUSINESS NUMBER : $vat_business_number");
            $vatStart = substr($vat_business_number, 0, 2);
            try {
                if (! $validator->validateFormat($vat_business_number)) {
                    rcp_errors()->add('invalid_company_vat_number', __('Please enter a valid VAT number', 'rcp-vat'), $dest_form);
                } else if (! empty($vatStart) && $vatStart !== $country_code) {
                    $msg = __('VAT Number and Country mismatch', 'rcp-vat') . '&nbsp;' . $vatStart . ' / ' . $country_code;
                    
                    rcp_errors()->add('vat_country_mismatch', $msg, $dest_form);
                } else {
                    $vat_amount = 0;
                }
            } catch (Exception $e) {
                
                $logText = "Error::" . $vat_business_number . ' ' . $e->getMessage();
                
                rcp_errors()->add('invalid_company_vat_number', $logText, $dest_form);
                rcp_vat_log($logText);
            }
        }
                
        $total_price = $level_price + $vat_amount;
        
        $result = array(
            'vat_label' => $vat_label,
            'country_name' => $country_name,
            'country_vat_rate' => $country_vat_rate,
            'level_price' => html_entity_decode(rcp_currency_filter($level_price)) . ' ' . $rcp_options['rcp_vat_without_tax_label'],
            'vat_amount' => html_entity_decode(rcp_currency_filter($vat_amount)),
            'total_price' => html_entity_decode(rcp_currency_filter($total_price)) . ' ' . $rcp_options['rcp_vat_with_tax_label'],
            'with_tax_label' => $rcp_options['rcp_vat_with_tax_label'],
            'without_tax_label' => $rcp_options['rcp_vat_without_tax_label']
        );
        
        rcp_vat_log($result);
        
        wp_send_json($result);
        wp_die();
    }

 

    /**
     * Get current (or given user id) user country based on meta value
     */
    function getUserCountry($user_id)
    {
        if (! $user_id) {
            $user_id = get_current_user_id();
        }
        
        $countryWC = get_user_meta($user_id, 'billing_country', true);
        $countryRCP = get_user_meta($user_id, 'rcp_country', true);
        /*
        rcp_vat_log($countryWC);
        rcp_vat_log($countryRCP);
        */
        $result = empty($countryWC) ? $countryRCP : $countryWC;
        
        return $result;
    }
    /**
     * Modify reccuring total to reflect VAT rate
     */
    function modify_total_recurring_with_VAT($total)
    {
        global $rcp_options;
        
        $result = $total . '&nbsp;' . $rcp_options['rcp_vat_with_tax_label']; // . ' (' . rcp_currency_filter ( $totalVAT ) . '&nbsp;' . $rcp_options ['rcp_vat_without_tax_label'] . ')';
        
        return $result;
    }
    /**
     * Modify total to reflect VAT rate
     */
    function modify_total_with_VAT($total)
    {
        global $rcp_options;
        
        $ht = $rcp_options['rcp_vat_without_tax_label'];
        $result = $total . '&nbsp;' . $ht; // . ' (' . rcp_currency_filter ( $totalVAT ) . '&nbsp;' . $ttc . ')';
        
        return $result;
    }
    /**
     * Get default country VAT rate
     */
    function getDefaultCountryRate($rates, $code, $currentRateTypeValue)
    {
        $default_local_rate = 0;
        try {
            $default_local_rate = $rates->country($code, $currentRateTypeValue);
        } catch (Exception $e) {
            $logText = "Warning " . $code . ' ' . $e->getMessage();
            
            try {
                
                $currentRateTypeValue = self::$defaultRateType;
                $default_local_rate = $rates->country($code, $currentRateTypeValue);
            } catch (Exception $e) {
                
                $logText = "Warning " . $code . ' ' . $e->getMessage();
            }
        }
        return $default_local_rate;
    }

    /**
     * Get country full name from its ISO code
     */
    function getCountryFromCode($code)
    {
        $countries = new DvK\Vat\Countries();
        $result = isset($countries->all()[$code]) ? $countries->all()[$code] : '';
        return $result;
    }

    /**
     * Get EU VAT (or user-defined) rate for given country
     */
    function getRateForCountry($code)
    {
        global $rcp_options;
        $optionName = 'rcp_vat_rate' . '_' . $code;
        $currentOptionValue = isset($rcp_options[$optionName]) ? $rcp_options[$optionName] : '';
        
        $rates = new DvK\Vat\Rates\Rates();
        $currentRateTypeValue = isset($rcp_options['rcp_vat_default_rate_type']) ? $rcp_options['rcp_vat_default_rate_type'] : self::$defaultRateType;
        $defaultRateValue = $this->getDefaultCountryRate($rates, $code, $currentRateTypeValue);
        
        $result = ! empty($currentOptionValue) ? $currentOptionValue : $defaultRateValue;
        
        return $result;
    }
    /**
     * Apply EU VAT rules and return rate to be applied if any, else false
     */
    function needToApplyVAT($user_id)
    {
        global $rcp_options;
        $result = false;
        $enable = $rcp_options['rcp_vat_tax_included'];
        if (! $enable) {
            return $result;
        }
        $countries = new DvK\Vat\Countries();
        $validator = new DvK\Vat\Validator();
        $billing_country = get_user_meta($user_id, 'billing_country', true); // [billing_country] => FR
        $vat_number = get_user_meta($user_id, 'rcp_company_vat_number', true);
        $businessCode = $this->getBusinessCountry();
        
        rcp_vat_log('needToApplyVAT ? Business:' . $businessCode . ' / User meta:' . $billing_country . ' / ' . $vat_number);
        
        if (empty($billing_country)) {
            $billing_country = $businessCode;
        }
        
        $businessCountry = isset($countries->all()[$businessCode]) ? $countries->all()[$businessCode] : '';
        rcp_vat_log('$businessCode: ' . $businessCode . ' -> ' . $businessCountry);
        
        try {
            
            if ($billing_country == $businessCode || $businessCountry == $billing_country) {
                
                $result = $this->getRateForCountry($billing_country);
                $msg = 'Your customer is based in the same country as you: charge your local VAT tariff.';
            } else if ($countries->inEurope($billing_country) && isset($vat_number)) { // $this->is_EU_Country($billing_country) && isset($vat_number)) {
                if (! $validator->validate($vat_number)) { // ! $this->is_valid_VAT($vat_number)) {
                                                           // $result = true;
                    $msg = 'Your customer is based in another EU country than you, but is a private person or company without a valid VAT number: charge your local VAT tariff.';
                    $result = $this->getRateForCountry($billing_country);
                } else {
                    $msg = 'Your customer is based in another EU country than you and has a valid VAT number: don’t charge VAT.';
                }
            } else {
                $msg = 'Your customer is based outside of the EU: don’t charge VAT, unless exceptions';
                $result = $this->getRateForCountry($billing_country);
            }
        } catch (Exception $e) {
            $logText = "Error::" . $vat_number . ' ' . $e->getMessage();
            
            rcp_vat_log($logText);
        }
        
        rcp_vat_log($msg);
        rcp_vat_log($result);
        
        return $result;
    }

    /**
     * Modify stripe subscription args before submitting to include VAT rate
     */
    function modify_rcp_stripe_create_subscription_args($sub_args, $obj)
    {
        rcp_vat_log('modify_rcp_stripe_create_subscription_args');
        rcp_vat_log($sub_args);
        global $rcp_options;
        $enable = $rcp_options['rcp_vat_tax_included'];
        
        $countries = new DvK\Vat\Countries();
        
        $userId = $sub_args["metadata"]["rcp_member_id"];
        
        $needToApplyVAT = $this->needToApplyVAT($userId);
        rcp_vat_log("need VAT for user " . $userId . ' ? ' . $needToApplyVAT);
        if ($enable && $needToApplyVAT !== false) {
            $sub_args[$this->stripeTaxPercentField] = $needToApplyVAT;
        }
        
        $userWP = get_user_by('ID', $userId);
        if ($userWP) {
            $company_vat_number = get_user_meta($userWP->ID, 'rcp_company_vat_number', true);
            
            $firstname = $userWP->user_firstname;
            $lastname = $userWP->user_lastname;
            $display_name = $userWP->display_name;
            $login = $userWP->user_login;
            
            $billing_address = get_user_meta($userWP->ID, 'billing_address_1', true);
            $billing_postcode = get_user_meta($userWP->ID, 'billing_postcode', true);
            $billing_country = get_user_meta($userWP->ID, 'billing_country', true);
            
            $sub_args["metadata"]['business_vat_id'] = $company_vat_number;
            $sub_args["metadata"]['firstname'] = $firstname;
            $sub_args["metadata"]['lastname'] = $lastname;
            $sub_args["metadata"]['display_name'] = $display_name;
            $sub_args["metadata"]['login'] = $login;
            $sub_args["metadata"]['user_id'] = $userId;
            $sub_args["metadata"]['billing_address_1'] = $billing_address;
            $sub_args["metadata"]['billing_postcode'] = $billing_postcode;
            $sub_args["metadata"]['rcp_vat_billing_country'] = $billing_country;
            $sub_args["metadata"]['rcp_vat_rate'] = $this->getRateForCountry($billing_country);
        }
        
        rcp_vat_log($sub_args);
        return $sub_args;
    }

    /**
     * Modify rcp subscription datas before submitting to include VAT rate
     */
    
    function modify_rcp_subscription_data($subscription_data)
    {
        global $rcp_options;
        rcp_vat_log('### modify_rcp_subscription_data before sending to gateway...');
        //rcp_vat_log($subscription_data);
        
        $enable = $rcp_options['rcp_vat_tax_included'];
        rcp_vat_log("RCP VAT enabled : " . $enable);
        $vat_rate = $this->needToApplyVAT($subscription_data['user_id']);
        rcp_vat_log("VAT needed : " . $vat_rate);
        if ($enable && $vat_rate !== false) {
            $subscription_data[$this->stripeTaxPercentField] = $vat_rate;
            $subscription_data["price"] = $this->getTotalPrice($subscription_data["price"], $vat_rate);
            $subscription_data["recurring_price"] = $this->getTotalPrice($subscription_data["recurring_price"], $vat_rate);
        }
        
        //rcp_vat_log($subscription_data);
        //rcp_vat_log('...done');
        return $subscription_data;
    }

    /**
     * Modify stripe customer args before submitting to include VAT rate
     */
    function modify_rcp_stripe_customer_create_args($customer_args, $object)
    {
        rcp_vat_log('### modify_rcp_stripe_customer_create_args');
        rcp_vat_log($customer_args);
        
        $modified_args = array_merge(array(), $customer_args);
        // add business_vat_id
        $userEmail = $customer_args['email'];
        $userWP = get_user_by('email', $userEmail);
        $company_vat_number = '';
        if ($userWP) {
            $company_vat_number = get_user_meta($userWP->ID, 'rcp_company_vat_number', true);
            
            $firstname = $userWP->user_firstname;
            $lastname = $userWP->user_lastname;
            $display_name = $userWP->display_name;
            $login = $userWP->user_login;
            $userID = $userWP->ID;
            
            $billing_address = get_user_meta($userWP->ID, 'billing_address_1', true);
            $billing_postcode = get_user_meta($userWP->ID, 'billing_postcode', true);
            $billing_country = get_user_meta($userWP->ID, 'billing_country', true);
        }
        
        $modified_args['business_vat_id'] = $company_vat_number;
        $modified_args['metadata'] = array(
            "firstname" => $firstname,
            'lastname' => $lastname,
            'display_name' => $display_name,
            'login' => $login,
            'user_id' => $userID,
            'billing_address' => $billing_address,
            'billing_postcode' => $billing_postcode,
            'rcp_vat_billing_country' => $billing_country,
            'rcp_vat_rate' => $this->getRateForCountry($billing_country)
        );
        
        rcp_vat_log($modified_args);
        return $modified_args;
    }

    /**
     * Save user fields on registration
     */
    function rcp_vat_save_user_fields_on_registration($posted, $user_id)
    {
        rcp_vat_log($posted);
        if (isset($posted['rcp_company_name'])) {
            update_user_meta($user_id, 'rcp_company_name', sanitize_text_field($posted['rcp_company_name']));
        }
        if (isset($posted['rcp_company_vat_number'])) {
            update_user_meta($user_id, 'rcp_company_vat_number', sanitize_text_field($posted['rcp_company_vat_number']));
        }
        if (! empty($posted['billing_address_1'])) {
            update_user_meta($user_id, 'billing_address_1', sanitize_text_field($posted['billing_address_1']));
        }
        if (! empty($posted['billing_postcode'])) {
            update_user_meta($user_id, 'billing_postcode', sanitize_text_field($posted['billing_postcode']));
        }
        if (! empty($posted['billing_country'])) {
            rcp_vat_log('save meta ' . $posted['billing_country'] . ' for user ' . $user_id);
            update_user_meta($user_id, 'billing_country', sanitize_text_field($posted['billing_country']));
        }
        rcp_vat_log(count($posted) . ' registration fields saved');
    }

    /**
     * Save user fields on profile save
     */
    function rcp_vat_save_user_fields_on_profile_save($user_id)
    {
        //rcp_vat_log("save meta for user " . $user_id);
        $this->rcp_vat_save_user_fields_on_registration($_POST, $user_id);
        //rcp_vat_log(count($posted) . ' profile fields saved');
    }

    /**
     * Adds the custom fields to the registration form and profile editor
     */
    function rcp_vat_rcp_add_user_fields($user_id = 0, $use_wc_billing_fields = 1)
    {
        if (! $user_id) {
            $user_id = get_current_user_id();
        }
        $company_name = get_user_meta($user_id, 'rcp_company_name', true);
        $company_vat_number = get_user_meta($user_id, 'rcp_company_vat_number', true);
        
        if ($use_wc_billing_fields) {
            $billing_address = get_user_meta($user_id, 'billing_address_1', true);
            $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
            $billing_country = get_user_meta($user_id, 'billing_country', true);
        } else {
            $billing_address = get_user_meta($user_id, 'rcp_address', true);
            $billing_postcode = get_user_meta($user_id, 'rcp_postcode', true);
            $billing_country = get_user_meta($user_id, 'rcp_country', true);
        }
        
        ?>
<h4><?php _e('VAT Fields','rcp-vat'); ?></h4>
<p>
	<label for="rcp_company_name"><?php _e( 'Company Name', 'rcp-vat' ); ?></label>
	<input name="rcp_company_name" id="rcp_company_name" type="text"
		value="<?php echo esc_attr( $company_name); ?>" />
</p>
<p>
	<label for="rcp_company_vat_number"><?php _e( 'Company VAT number', 'rcp-vat' ); ?></label>
	<input name="rcp_company_vat_number" id="rcp_company_vat_number"
		type="text" value="<?php echo esc_attr( $company_vat_number); ?>" />
</p>
<?php  if ($use_wc_billing_fields) { ?>
<p>
	<label for="billing_address_1"><?php _e( 'Address', 'rcp-vat' ); ?></label>
	<input name="billing_address_1" id="billing_address_1" type="text"
		value="<?php echo esc_attr( $billing_address); ?>" />
</p>
<p>
	<label for="billing_postcode"><?php _e( 'Postcode', 'rcp-vat' ); ?></label>
	<input name="billing_postcode" id="billing_postcode" type="text"
		value="<?php echo esc_attr( $billing_postcode); ?>" />
</p>
<p>	
		<?php
            echo $this->getMemberSelectCountryList('billing_country', 'billing_country', __('Country', 'rcp-vat'), 'get_user_meta', $user_id, 'billing_country', true);
            
            ?>
	</p>

<?php
        }
    }

    function rcp_vat_rcp_add_member_edit_fields($user_id = 0)
    {
        echo $this->rcp_vat_rcp_add_user_fields($user_id);
    }

    /**
     * Validate user fields and show errors if any
     */
    function validateUserFields($posted, $dest_form)
    {
        rcp_vat_log('### VALIDATE ### rcp_vat_rcp_validate_user_fields_on_register');
        $vatStart = '';
        // check VAT company number
        if (! empty($posted['rcp_company_vat_number'])) {
            
            $companyVatNumber = trim($posted['rcp_company_vat_number']);
            $vatStart = substr($companyVatNumber, 0, 2);
            $validator = new DvK\Vat\Validator();
            try {
                if (! $validator->validateExistence($companyVatNumber)) {
                    
                    rcp_errors()->add('invalid_company_vat_number', __('Please enter a valid VAT number', 'rcp-vat'), $dest_form);
                }
            } catch (Exception $e) {
                $logText = "Error::" . $companyVatNumber . ' ' . $e->getMessage();
                
                rcp_errors()->add('invalid_company_vat_number', $logText, $dest_form);
                rcp_vat_log($logText);
            }
        }
        
        if ($this->use_wc_billing_fields) {
            
            // all necessary checks
            
            if (empty($posted['billing_address_1'])) {
                rcp_errors()->add('invalid_address', __('Please enter your address', 'rcp-vat'), $dest_form);
            }
            
            if (empty($posted['billing_postcode'])) {
                rcp_errors()->add('invalid_postcode', __('Please enter your postcode', 'rcp-vat'), $dest_form);
            }
            if (empty($posted['billing_country'])) {
                rcp_errors()->add('invalid_country', __('Please enter your country', 'rcp-vat'), $dest_form);
            } else if (! empty($vatStart) && $vatStart !== $posted['billing_country']) {
                $msg = __('VAT Number and Country mismatch', 'rcp-vat') . '&nbsp;' . $vatStart . ' / ' . $posted['billing_country'];
                
                rcp_errors()->add('vat_country_mismatch', $msg, $dest_form);
            } else if (! empty($vatStart)) {
                $msg = __('VAT Number and Country match', 'rcp-vat') . ' ' . $vatStart . ' / ' . $posted['billing_country'];
                rcp_vat_log($msg);
            } else {
                $msg = __('VAT Not applicable', 'rcp-vat');
                rcp_vat_log($msg);
            }
        }
    }

    /**
     * Determines if there are problems with the registration data submitted
     */
    function rcp_vat_rcp_validate_user_fields_on_profile_update($posted, $user_id)
    {
        $this->validateUserFields($posted);
    }

    /**
     * Validate user fields at register time
     */
    
    function rcp_vat_rcp_validate_user_fields_on_register($posted)
    {
        if (rcp_get_subscription_id()) {
            rcp_vat_log('### VALIDATE ### no subscription id');
            return;
        }
        
        $this->validateUserFields($posted, 'register');
    }

    /**
     * Get user country list with selected one
     */
    public function getMemberSelectCountryList($id, $name, $label, $selectedCallback, $arg1, $arg2, $arg3)
    {
        $user_id = get_current_user_id();
        $billing_country = get_user_meta($user_id, 'billing_country', true);
        
        $html = '<label for="' . $name . '">' . $label . '</label>';
        $html .= '<select id="' . $id . '" name="' . $name . '">';
        $countries = new DvK\Vat\Countries();
        foreach ($countries->all() as $ISO => $countryName) {
            $html .= '<option ';
            $html .= 'value="' . $ISO . '" ';
            
            $selected = '';
            $callbackReturn = empty($billing_country) ? $this->getBusinessCountry() : $billing_country; // call_user_func($selectedCallback, $arg1, $arg2, $arg3);
            if ($callbackReturn == $ISO) {
                $selected = 'selected';
                $html .= " selected='selected'";
            }
            
            $html .= '>';
            $html .= $countryName;
            $html .= '</option>';
        }
        
        $html .= '</select>';
        return $html;
    }

    /**
     * Get EU rate types
     */
    static function getRateTypes()
    {
        return self::rateTypes;
    }

    
    /**
     * Get seller business country set in settings
     */
    
    public function getBusinessCountry()
    {
        global $rcp_options;
        return isset($rcp_options['rcp_vat_business_country']) ? $rcp_options['rcp_vat_business_country'] : '';
    }

    
    /**
     * Render settings page - added after RCP Paiements settings.
     */
    
    public function settings_fields($rcp_options)
    {
        ?>
<table class="form-table">
	<tr valign="top">
		<th colspan=2><h3><?php _e( 'RCP VAT Settings (with Stripe Only)', 'rcp-vat' ); ?></h3></th>
	</tr>

	<tr valign="top">
		<th><label for="rcp_settings[rcp_vat_tax_included]"><?php _e( 'Enabled?', 'rcp-vat' ); ?></label>
		</th>
		<td><label> <input type="checkbox"
				id="rcp_settings[rcp_vat_tax_included]"
				name="rcp_settings[rcp_vat_tax_included]"
				<?php echo !empty( $rcp_options['rcp_vat_tax_included'] ) ? 'checked="checked"' : ''; ?> />
						<?php _e( 'If checked, taxes will be added on all new user subscription created from now on.', 'rcp-vat' ); ?>
					</label>
			<p class="description"><?php _e( 'Ex. $10 Subscription: If Enable : $10+VAT will be displayed on site, charged, and shown inside invoice.', 'rcp-vat' ); ?></p>
		</td>
	</tr>
	<tr valign="top">
		<th><label for="rcp_settings[rcp_vat_business_country]"><?php _e( 'Business country', 'rcp-vat' ); ?></label>
		</th>
		<td><select id="rcp_settings[rcp_vat_business_country]"
			name="rcp_settings[rcp_vat_business_country]">

				<?php
        $countries = new DvK\Vat\Countries();
        $allCountries = $countries->all(); // a
        foreach ($allCountries as $code => $name) {
            
            $selectedAttr = selected($this->getBusinessCountry(), $code, false);
            
            $newOption = '<option value="' . $code . '" ' . $selectedAttr . '>' . $name . '</option>';
            echo $newOption;
        }
        ?>

				
		</select>
			<p class="description"><?php _e( 'Set the country from which you sell.', 'rcp-vat' ); ?></p>
		</td>
	</tr>

	<tr>
		<th><label for="rcp_settings[rcp_vat_default_rate_type]"><?php _e( 'Default rate type for your business', 'rcp-vat' );echo ' (';_e('currently ');echo $this->getRateForCountry($this->getBusinessCountry()) . '%) : '; ?></label>
		</th>
		<td><select id="rcp_settings[rcp_vat_default_rate_type]"
			name="rcp_settings[rcp_vat_default_rate_type]">

				<?php
        
        $currentRateTypeValue = isset($rcp_options['rcp_vat_default_rate_type']) ? $rcp_options['rcp_vat_default_rate_type'] : self::$defaultRateType;
        
        foreach (self::$rateTypes as $key => $name) {
            
            $selectedAttr = selected($key, $currentRateTypeValue, false);
            
            $newOption = '<option value="' . $key . '" ' . $selectedAttr . '>' . $name . '</option>';
            echo $newOption;
        }
        
        ?>

				
		</select></td>
	</tr>


	<tr>
		<th><label for="rcp_settings[rcp_vat_without_tax_label]"><?php _e( 'Without tax price suffix', 'rcp-vat' ); ?></label>
		</th>
		<td><input class="regular-text"
			id="rcp_settings[rcp_vat_without_tax_label]" style="width: 300px;"
			name="rcp_settings[rcp_vat_without_tax_label]"
			value="<?php if(isset($rcp_options['rcp_vat_without_tax_label'])) { echo $rcp_options['rcp_vat_without_tax_label']; } ?>" />
			<p class="description"><?php _e('Enter price label suffix when no VAT applied.', 'rcp-vat'); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="rcp_settings[rcp_vat_with_tax_label]"><?php _e( 'With tax price suffix', 'rcp-vat' ); ?></label>
		</th>
		<td><input class="regular-text"
			id="rcp_settings[rcp_vat_with_tax_label]" style="width: 300px;"
			name="rcp_settings[rcp_vat_with_tax_label]"
			value="<?php if(isset($rcp_options['rcp_vat_with_tax_label'])) { echo $rcp_options['rcp_vat_with_tax_label']; } ?>" />
			<p class="description"><?php _e('Enter price label suffix when VAT applied.', 'rcp-vat'); ?></p>
		</td>
	</tr>
	<tr>
		<td>
	<?php
        
        $countries = new DvK\Vat\Countries();
        $euCountries = $countries->europe();
        $rates = new DvK\Vat\Rates\Rates();
        
        $fieldSetStyle = 'border:solid #C4C4C4 1px;padding:1em;margin:5px 0;width:40%;';
        $currentRateTypeValue = isset($rcp_options['rcp_vat_default_rate_type']) ? $rcp_options['rcp_vat_default_rate_type'] : self::$defaultRateType;
        $euFields = '<fieldset style="' . $fieldSetStyle . '"><legend class="">EU Countries Rates (defaults to <em>' . $currentRateTypeValue . '</em> if available, else <em>' . self::$defaultRateType . '</em>):</legend>';
        
        foreach ($euCountries as $code => $name) {
            $newOption = $this->createOptionalRateOption($rates, $code, $name, $currentRateTypeValue, $rcp_options);
            $euFields .= $newOption;
        }
        $euFields .= '</fieldset>';
        
        echo $euFields;
        
        echo '</td></tr><tr><td>';
        
        $leftCountries = array();
        $leftCountries = array_diff($countries->all(), $countries->europe());
        
        $otherFields = '<fieldset style="' . $fieldSetStyle . '"><legend class="">Other Countries Rates (defaults to <em>' . $currentRateTypeValue . '</em> if available, else <em>' . self::$defaultRateType . '</em>):</legend>';
        
        foreach ($leftCountries as $code => $name) {
            $newOption = $this->createOptionalRateOption($rates, $code, $name, $currentRateTypeValue, $rcp_options);
            $otherFields .= $newOption;
        }
        $otherFields .= '</fieldset>';
        
        echo $otherFields;
        
        ?>
	</td>
	</tr>


</table><?php
    }

    /**
     * Create rate setting option for this country
     */
    function createOptionalRateOption($rates, $code, $name, $currentRateTypeValue, $rcp_options)
    {
        $default_local_rate = $this->getDefaultCountryRate($rates, $code, $currentRateTypeValue);
        $optionName = 'rcp_vat_rate' . '_' . $code;
        
        $currentOptionValue = isset($rcp_options[$optionName]) ? $rcp_options[$optionName] : '';
        $custom_setting = empty($currentOptionValue) ? false : true;
        $localVATText = __('Local VAT %', 'rcp-vat');
        $entercustomVAT = __('Enter your custom VAT rate in %');
        $result = '<p>';
        $result .= '<span style="font-weight:700;color:' . ($custom_setting ? 'blueviolet' : '') . ';">' . $name . ':</span>';
        $result .= '<input class="regular-text" id="rcp_settings[' . $optionName . ']" style="width: 300px;" name="rcp_settings[' . $optionName . ']"
			placeholder="' . $default_local_rate . '" value="' . $currentOptionValue . '" />';
        $result .= '<span class="description">' . $entercustomVAT . ' (' . $code . ' / ' . $currentRateTypeValue . ' / ' . $default_local_rate . ')</span>';
        $result .= '</p>';
        
        return $result;
    }

    /**
     * Render the country field for member details.
     */
    public function member_details($user_id)
    {
        $country = get_user_meta($user_id, 'rcp_country', true);
        $countries = $this->get_countries();
        if (empty($country)) {
            return;
        }
        ?>
<tr class="form-field">
	<th scope="row" valign="top">
				<?php _e( 'Country', 'rcp-vat' ); ?>
			</th>
	<td>
				<?php echo $countries[$country]; ?>
			</td>
</tr><?php
    }
}