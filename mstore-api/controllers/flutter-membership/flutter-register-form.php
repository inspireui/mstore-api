<?php

class FlutterRegisterForm
{
    /**
     * @var string
     */
    private $template                   = '';
    /**
     * @var int
     */
    private $lid                        = 0;
    /**
     * @var array
     */
    private $shortcodeAttr              = null;
    /**
     * @var array
     */
    private $fields                     = null;
    /**
     * @var string
     */
    private $requiredFields             = [];
    /**
     * @var string
     */
    private $globalCss                  = null;
    /**
     * @var array
     */
    private static $errors              = [];
    /**
     * @var array
     */
    private static $dataFromCookie      = [];
    /**
     * @var array
     */
    private $conditionalLogicFields     = [];
    /**
     * @var array
     */
    private $conditionalTextFields      = [];
    /**
     * @var array
     */
    private $uniqueFields               = [];
    /**
     * @var array
     */
    private $exceptionFields            = [];
    /**
     * @var string
     */
    private $passwordGenerated          = '';
    /**
     * @var int
     */
    private $isModal                    = 0;
    /**
     * @var array
     */
    private $fieldsNames                = [];
    /**
     * @var bool
     */
    private static $alreadySet          = false;

    /**
     * @param none
     * @return none
     */
    public function __construct()
    {

        if ( self::$alreadySet ){
            // in order to stop the register of actions multiple times.
            return;
        } else {
            self::$alreadySet = true;
        }

        // register form:
        //add_shortcode( 'ihc-register', [ $this, 'form' ] );

        // register lite form:
        //add_shortcode( 'ihc-register-lite', [ $this, 'liteForm' ] );

        // register processing:
        add_action( 'ihc_action_public_post', [ $this, 'save' ], 999, 2 );

        // register lite processing:
        add_action( 'ihc_action_public_post', [ $this, 'saveRegisterLite' ], 999, 2 );



        // add social form after the register form
        add_filter( 'ihc_filter_after_register_form_output', [ $this, 'socialForm' ], 1, 1 );

        // add social hidden field
        add_filter( 'ihc_filter_register_form_extra_form_fields', [ $this, 'socialHiddenField'], 1, 1 );

        // does we have cookies from social media ?
        add_action( 'init', [ $this, 'checkCookies' ] );

        // ======= after register events =======
        // do opt in
        add_action( 'ihc_register_action_after_insert', [ $this, 'doOptIn'], 100, 5 );
        // double email verification
        add_action( 'ihc_register_action_after_insert', [ $this, 'doubleEmailVerification'], 1, 5 );
        // individual page
        add_action( 'ihc_register_action_after_insert', [ $this, 'doIndividualPage'], 1, 5 );
        // verification code
        add_action( 'ihc_register_action_after_insert', [ $this, 'incrementInvitationCode'], 1, 5 );
        // ======= end of after register events =======
    }

    /**
     * Workaround in order to keep ump_before_submit_form filter.
     * This action will fire the ump_before_submit_form filter, that it's used in older versions of ump.
     * It used to show up the checkout section and UAP referral select before the submit button .
     * ump_before_submit_form is present in classes/Chackout.php.
     * @param int
     * @param array
     * @return none. echo html
     */
    public function legacyFilter( $uid=0, $fields=[] )
    {
        $this->setLid();
        $string = '';
        $string = apply_filters( 'ump_before_submit_form', $string, true, 'create', $this->lid );
        echo esc_ump_content($string);
    }

    /**
     * This function will return the register form. Shortcode [ihc-register] .
     * @param array
     * @return string
     */
    public function form( $attr=[] )
    {

        // legacy action in order to print the checkout section. modify since 11.9
        add_action( 'ihc_action_template_form_file_before_submit_button', [ $this, 'legacyFilter' ], 999, 2  );

        $this->shortcodeAttr = $attr;
        $this->setLid();// via get, post, option from admin or shortcode attribute
        $this->setTemplate();
        $this->setFields(); // set the register fields
        $this->conditionalLogic();
        $this->buildCSS(); // the custom css
        $this->buildJS(); // move some settings into js
      	$str = '';// final output

        do_action( 'ihc_action_public_register_before_print_form', $this->lid, $this->shortcodeAttr );

      	$oldLogs = new \Indeed\Ihc\OldLogs();
      	$s = $oldLogs->FGCS();
        $logg = $oldLogs->GCP();
      	if ( $s === true || (int)$s > 0 || $logg === true ){
      			$str .= ihc_public_notify_trial_version();
      	}

      	$user_type = ihc_get_user_type();
        if ( $user_type == 'admin' && empty( $this->shortcodeAttr['is_preview'] ) ){
            return '<div class="ihc-warning-message"><strong>' . esc_html__('Administrator Info', 'ihc') . '</strong>' . esc_html__(': Register Form is not showing up once you\'re logged. You may check how it it looks for testing purpose by opening the page into a separate incognito browser window.','ihc') . '<i>' . esc_html__('This message will not be visible for other users','ihc') .'</i></div>';
        } else if ( $user_type !== 'unreg' && empty( $this->shortcodeAttr['is_preview'] ) ){
            return '';
        }

      	if ( isset( $_GET['ihc_register'] ) ){
      			return '';
      	}

      	$showForm = true;
      	$showForm = apply_filters( 'ump_show_register_form', $showForm );
      	if ( !$showForm ){
      			return '';
      	}

        $data = [
                    'fields'            => $this->fields,
                    'user_data'         => false,
                    'form_type'         => isset( $this->shortcodeAttr['is_modal'] ) && $this->shortcodeAttr['is_modal'] ? 'modal' : 'register',
                    'uid'               => 0,
                    'errors'            => self::$errors,
                    'form_class'        => 'ihc-form-create-edit',
                    'form_name'         => 'createuser',
                    'form_id'           => 'createuser',
                    'extra_fields'      => [
                                              [
                                                  'type'        => 'hidden',
                                                  'name'        => 'ihcFormType',
                                                  'value'       => isset( $this->shortcodeAttr['is_modal'] ) && $this->shortcodeAttr['is_modal'] ? 'modal' : 'register',
                                              ],
                                              [
                                                  'type'        => 'hidden',
                                                  'name'        => 'ihcaction',
                                                  'value'       => 'register',
                                              ],
                                              [
                                                  'type'        => 'hidden',
                                                  'name'        => 'ihc_user_add_edit_nonce',
                                                  'value'       => wp_create_nonce( 'ihc_user_add_edit_nonce' ),
                                              ],
                    ],
                    'submit_bttn_label'   => get_option( 'ihc_register_button_label' , esc_html__('Register', 'ihc') ),
                    'submit_bttn_name'    => 'Submit',
                    'submit_bttn_id'      => 'ihc_submit_bttn',
        ];
        if ( $data['submit_bttn_label'] === '' ){
            $data['submit_bttn_label'] = esc_html__('Register', 'ihc');
        }

        if ( $this->lid !== null && $this->lid > 0 ){
            $data['extra_fields'][] = [
                'type'        => 'hidden',
                'name'        => 'lid',
                'value'       => $this->lid,
            ];
        }

        // add exceptions. fields that are conditional logic and required in the same time.
        if ( $this->exceptionFields !== null && count( $this->exceptionFields ) > 0 ){
            $data['extra_fields'][] = [
              'type'        => 'hidden',
              'name'        => 'ihc_exceptionsfields',
              'id'          => 'ihc_exceptionsfields',
              'value'       => implode(',', $this->exceptionFields ),
            ];
        }

        if ( !isset( $data['extra_fields' ] ) ){
            $data['extra_fields'] = [];
        }
        $data['extra_fields'] = apply_filters( 'ihc_filter_register_form_extra_form_fields', $data['extra_fields'] );

        // is preview ?
        if ( !empty( $this->shortcodeAttr['is_preview'] ) ){
            $data['disableSubmit'] = true;
        }

        // form template
        $templateParts = explode( '-', $this->template );
        $templateNo = isset( $templateParts[2] ) ? (int)$templateParts[2] : 1;
        if ( $templateNo < 1 ){
            $templateNo = 1;
        }
        $filename = 'form-template-' . $templateNo . '.php';
        $template = IHC_PATH . 'public/views/form-templates/' . $filename;
        $template = apply_filters( 'ihc_filter_on_load_template', $template, $filename );

        // html
        $view = new \Indeed\Ihc\IndeedView();
        $output = $view->setTemplate( $template )
                       ->setContentData( $data, true )
                       ->getOutput();

        $output = apply_filters( 'ihc_filter_after_register_form_output', $output );

      	$str .= '<div class="iump-register-form ' . $this->template . '">' . $output . '</div>';

      	return $str;
    }

    /**
      * This function will set the membership id ( level id - lid ) via get, post, option from admin or shortcode attribute.
      * @param none
      * @return object
      */
    public function setLid()
    {
        global $wp_query;
        $membershipSlug = isset( $wp_query->query_vars['iump-membership-slug'] ) ? sanitize_text_field($wp_query->query_vars['iump-membership-slug']) : false;

        // set membership id
        if ( isset( $this->shortcodeAttr['level'] ) && $this->shortcodeAttr['level'] !== '' && $this->shortcodeAttr['level'] !== false ){
            $this->lid = $this->shortcodeAttr['level'];
        } else {
            $this->lid = get_option('ihc_register_new_user_level');
        }
        if ( isset( $_GET['lid'] ) && $_GET['lid'] !== '' ){
            $this->lid = sanitize_text_field( $_GET['lid'] );
        } else if ( $membershipSlug !== false ){
            $membership = \Indeed\Ihc\Db\Memberships::getOneByName( $membershipSlug );
            if ( isset( $membership['id'] ) ){
                $this->lid = $membership['id'];
            }
        }

        if ( isset( $_POST['lid'] ) && $_POST['lid'] !== ''  ){
            $this->lid = sanitize_text_field( $_POST['lid'] );
        }
        return $this;
    }

    /**
      * Set the template from shortcode attribute, input or the value set in admin section.
      * @param string
      * @return object
      */
    public function setTemplate( $input=null )
    {
        // set template
        if ( $input !== null && $input !== '' && $input !== false ){
            $this->template = $input;
        }
        if ( isset( $this->shortcodeAttr['template'] ) && $this->shortcodeAttr['template'] !== '' && $this->shortcodeAttr['template'] !== false ){
            $this->template = $this->shortcodeAttr['template'];
            return $this;
        }
        if ( $this->template === null || $this->template === '' || $this->template === false ){
            $this->template = get_option( 'ihc_register_template', 'ihc-register-1' );
        }
        return $this;
    }

    /**
     * Set the register form fields, that are set into the admin section.
     * display_on_modal = used in modal.
     * display_public_reg = used in normal register.
     * @param none
     * @return object
     */
    public function setFields()
    {
        $this->fields = ihc_get_user_reg_fields();// get fields from db
        // remove payment_select from fields
        $key = ihc_array_value_exists( $this->fields, 'payment_select', 'name' );
        if ( $key !== false ){
            unset( $this->fields[$key] );
        }
        // remove dynamic price from fields
        $key = ihc_array_value_exists( $this->fields, 'ihc_dynamic_price', 'name' );
        if ( $key !== false ){
            unset( $this->fields[$key] );
        }
        // remove ihc coupons from fields
        $key = ihc_array_value_exists( $this->fields, 'ihc_coupon', 'name' );
        if ( $key !== false ){
            unset( $this->fields[$key] );
        }

        // extra check for recaptcha
        $key = ihc_array_value_exists( $this->fields, 'recaptcha', 'name' );
        if ( $key !== false ){
            $recaptchaType = get_option( 'ihc_recaptcha_version' );
            if ( $recaptchaType !== false && $recaptchaType == 'v3' ){
                $recaptchaKey = get_option('ihc_recaptcha_public_v3');
            } else {
                $recaptchaKey = get_option('ihc_recaptcha_public');
            }
            if ( empty( $recaptchaKey ) ){
                // in case we dont have keys for recaptcha . unset it
                unset( $this->fields[$key] );
            }
        }
        // sort the fields
        ksort( $this->fields );

        // show only the fields that are selected on backend
        $keyToSearch = ( isset( $this->shortcodeAttr['is_modal'] ) && (int)$this->shortcodeAttr === 1 ) ? 'display_on_modal' : 'display_public_reg';
        if ( $this->isModal ){
            $keyToSearch = 'display_on_modal';
        }

        // loop through form fields, and decide what to show.
        foreach ( $this->fields as $fieldKey => $fieldArray ){
            if ( (int)$fieldArray[$keyToSearch] === 0 ){
                unset( $this->fields[$fieldKey] );
            } else {
                // Targeting Memberships
                if ( isset( $fieldArray['target_levels'] ) && $fieldArray['target_levels'] !== '' ){
                    $targetMemberships = explode( ',', $fieldArray['target_levels'] );
                    if ( count( $targetMemberships ) > 0 ){
                        $showField = false;
                        foreach ( $targetMemberships as $targetMembership ){
                            if ( $targetMembership === $this->lid ){
                                $showField = true;
                            }
                        }
                        if ( !$showField ){
                            unset( $this->fields[$fieldKey] );
                            continue;
                        }
                    }
                }
                // end of Targeting Memberships

                // set the field parent id & class, required, inside label, multiple values
                $this->fields[$fieldKey]['parent_field_class']    = 'iump-form-' . $fieldArray['name'];
                $this->fields[$fieldKey]['parent_field_id']       = 'ihc_reg_' . $fieldArray['name'] . '_' . rand(1,10000);
                $this->fields[$fieldKey]['multiple_values']       = isset( $fieldArray['values'] ) && $fieldArray['values'] ? ihc_from_simple_array_to_k_v( $fieldArray['values'] ) : false;
                $this->fields[$fieldKey]['label_inside']          = isset( $fieldArray['native_wp'] ) && $fieldArray['native_wp'] ? esc_html__( $fieldArray['label'], 'ihc') : ihc_correct_text( $fieldArray['label'] );
                $this->fields[$fieldKey]['required_field']        = isset( $fieldArray['req'] ) && $fieldArray['req'] ? $fieldArray['req'] : false;
                $this->fields[$fieldKey]['disabled_field']        = false;
                if ( isset( $fieldArray['plain_text_value'] ) && $fieldArray['plain_text_value'] !== '' ){
                    $this->fields[$fieldKey]['value_to_print'] = $fieldArray['plain_text_value'];
                }

                // value from post or value from cookie if its case
                if ( isset( $_POST[ $fieldArray['name'] ] ) && $_POST[ $fieldArray['name'] ] !== '' ){
                    $this->fields[$fieldKey]['value_to_print'] = sanitize_text_field($_POST[ $fieldArray['name'] ]);
                } else if ( isset( self::$dataFromCookie[ $fieldArray['name'] ] ) && self::$dataFromCookie[ $fieldArray['name'] ] !== '' ){
                    $this->fields[$fieldKey]['value_to_print'] = self::$dataFromCookie[ $fieldArray['name'] ];
                }

                // is this field required, this array will go into js. we exclude the pass1 and pass2
                if ( $this->fields[$fieldKey]['required_field'] !== false ){
                    $this->requiredFields[] = $fieldArray['name'];
                }

                // add conditional_text && unique_value_text  into js
                switch ( $this->fields[$fieldKey]['type'] ){
                    case 'conditional_text':
                      $this->conditionalTextFields[] = $fieldArray['name'];
                      break;
                    case 'unique_value_text':
                      $this->uniqueFields[] = $fieldArray['name'];
                      break;
                }

                // special settings for special fields.
                switch ( $this->fields[$fieldKey]['name'] ){
                    case 'ihc_social_media':
                    case 'recaptcha':
                    case 'tos':
                      $this->fields[$fieldKey]['hide_outside_label'] = true;
                      $this->fields[$fieldKey]['label_inside'] = '';
                      break;
                    case 'ihc_memberlist_accept':
                      $this->fields[$fieldKey]['hide_outside_label'] = true;
                      $this->fields[$fieldKey]['value_to_print'] = isset( $this->fields[$fieldKey]['ihc_memberlist_accept_checked'] ) ? (int)$this->fields[$fieldKey]['ihc_memberlist_accept_checked'] : null;
                      break;
                    case 'ihc_optin_accept':
                      $this->fields[$fieldKey]['hide_outside_label'] = true;
                      $this->fields[$fieldKey]['value_to_print'] = isset( $this->fields[$fieldKey]['ihc_optin_accept_checked'] ) ? (int)$this->fields[$fieldKey]['ihc_optin_accept_checked'] : null;
                      break;
                }
            }
        }

        // switch the type of tos field from checkbox to 'tos'
        $key = ihc_array_value_exists( $this->fields, 'tos', 'name' );
        if ( $key !== false ){
            $this->fields[$key]['type'] = 'tos';
        }
        // switch the type of state field if its available
        $key = ihc_array_value_exists( $this->fields, 'ihc_state', 'name' );
        if ( $key !== false ){
            // switch the type of text field from checkbox to 'ihc_state'
            $this->fields[$key]['type'] = 'ihc_state';
        }

        // create an array with all fields name
        foreach ( $this->fields as $fieldKey => $fieldArray ){
            $this->fieldsNames[] = $fieldArray['name'];
        }

        return $this;
    }

    /**
     * This function will add the custom css set in admin section.
     * @param string
     * @return string
     */
    public function buildCSS( $cssOption='ihc_register_custom_css' )
    {
        // the custom css set in the admin section
        $globalCss = get_option( $cssOption, '' ); //add custom css to global css
        $globalCss .= $this->globalCss;
        if ( $globalCss === '' ){
            return;
        }
        wp_register_style( 'dummy-handle', false );
        wp_enqueue_style( 'dummy-handle' );
        wp_add_inline_style( 'dummy-handle', stripslashes( $globalCss ) );
    }

    /**
     * This function add the js used in register process, also add some settings from server side to client side. like conditional, required fields, etc
     * @param none
     * @return string
     */
    public function buildJS()
    {
        global $wp_version;

        if ( !isset( $GLOBALS['wp_scripts']->registered['ihc-public-dynamic'] ) ){
            wp_register_script( 'ihc-public-dynamic', IHC_URL . 'assets/js/public.js', ['jquery'], 12.3 );
        }
        if ( !isset( $GLOBALS['wp_scripts']->registered['ihc-public-register-form'] ) ){
            wp_register_script( 'ihc-public-register-form', IHC_URL . 'assets/js/IhcRegisterForm.js', ['jquery'], 12.3 );
        }

        if ( version_compare ( $wp_version , '5.7', '>=' ) ){
            if ( count( $this->requiredFields ) > 0 ){
                wp_add_inline_script( 'ihc-public-register-form', "var ihc_register_required_fields='" . json_encode( $this->requiredFields ) . "';" );
            }
            if ( count( $this->conditionalLogicFields ) > 0 ){
                wp_add_inline_script( 'ihc-public-register-form', "var ihc_register_conditional_logic='" . json_encode( $this->conditionalLogicFields ) . "';" );
            }
            if ( count( $this->conditionalTextFields ) > 0 ){
                wp_add_inline_script( 'ihc-public-register-form', "var ihc_register_conditional_text='" . json_encode( $this->conditionalTextFields ) . "';" );
            }
            if ( count( $this->uniqueFields ) > 0 ){
                wp_add_inline_script( 'ihc-public-register-form', "var ihc_register_unique_fields='" . json_encode( $this->uniqueFields ) . "';" );
            }
            if ( count( $this->fieldsNames ) > 0 ){
                wp_add_inline_script( 'ihc-public-register-form', "var ihc_register_fields='" . json_encode( $this->fieldsNames ) . "';" );
            }
            wp_add_inline_script( 'ihc-public-register-form', "ihcPasswordStrengthLabels='" . json_encode( [esc_html__('Very Weak', 'ihc'), esc_html__('Weak', 'ihc'), esc_html__('Good', 'ihc'), esc_html__('Strong', 'ihc')] ) . "';" );
        } else {
            if ( count( $this->requiredFields ) > 0 ){
                wp_localize_script( 'ihc-public-register-form', 'ihc_register_required_fields', json_encode( $this->requiredFields ) );
            }
            if ( count( $this->conditionalLogicFields ) > 0 ){
                wp_localize_script( 'ihc-public-register-form', 'ihc_register_conditional_logic', json_encode( $this->conditionalLogicFields ) );
            }
            if ( count( $this->conditionalTextFields ) > 0 ){
                wp_add_inline_script( 'ihc-public-register-form', 'ihc_register_conditional_text', json_encode( $this->conditionalTextFields ) );
            }
            if ( count( $this->uniqueFields ) > 0 ){
                wp_add_inline_script( 'ihc-public-register-form', 'ihc_register_unique_fields', json_encode( $this->uniqueFields ) );
            }
            if ( count( $this->uniqueFields ) > 0 ){
                wp_add_inline_script( 'ihc-public-register-form', 'ihc_register_fields', json_encode( $this->fieldsNames ) );
            }
            wp_add_inline_script( 'ihc-public-register-form', 'ihcPasswordStrengthLabels', json_encode( [esc_html__('Very Weak', 'ihc'), esc_html__('Weak', 'ihc'), esc_html__('Good', 'ihc'), esc_html__('Strong', 'ihc') ] ) );
        }

        wp_enqueue_script( 'ihc-public-register-form' );
    }

    /**
     * This functions search for conditional logic fields, it will hive those that the user should not see.
     * @param none
     * @return none
     */
    private function conditionalLogic()
    {
        if ( count( $this->fields ) === 0 ){
            return '';
        }
        foreach ( $this->fields as $fieldKey => $field ){
            if ( empty( $field['conditional_logic_corresp_field'] ) || $field['conditional_logic_corresp_field'] === -1 ){
                continue;
            }
            // Js action
            $key = ihc_array_value_exists( $this->fields, $field['conditional_logic_corresp_field'], 'name' );

            if ( $key === false || empty( $field['type'] ) ){
                continue;
            }

            //$value = false;
            $value = $this->getFieldDefaultValue( $field['conditional_logic_corresp_field'] );

            $checkConditionalLogic = 0;

            if ( $field['conditional_logic_cond_type'] === 'has' ){
                // has value
                if ( $field['conditional_logic_corresp_field_value'] === $value ){
                    $checkConditionalLogic = 1;
                }
            } else {
                // contain value
                if ( is_string( $value ) && is_string( $field['conditional_logic_corresp_field_value'] )
                && strpos( $value, $field['conditional_logic_corresp_field_value'] ) !== false ){
                    $checkConditionalLogic = 1;
                }
            }

            $show = ( $field['conditional_logic_show'] === 'yes' ) ? 1 : 0;

            if ( $show ){
                // 'yes'
                $no_on_edit = $checkConditionalLogic;
            } else {
                // 'no'
                $no_on_edit = !$checkConditionalLogic;
            }

            // get field type
            $key = ihc_array_value_exists( $this->fields, $field['conditional_logic_corresp_field'], 'name' );
            if ( $key !== false && isset( $this->fields[$key] ) ){
                $type = $this->fields[$key]['type'];
            }

            $this->conditionalLogicFields[] = [
                'type'                => $type,
                'field_to_check'      => $field['conditional_logic_corresp_field'],
                'target_parent_id'    => $field['parent_field_id'],
                'target_field'        => $field['name'],
                'show'                => $show,
            ];

            if ( !empty( $field['req'] ) && empty( $no_on_edit ) ){
                // exceptions are the fields that are required but are not show up in the form
                // because it has some extra settings that prevent to show for this membership or for
                // this combinations of fields
                $this->exceptionFields[] = $field['name'];
            }

            if ( empty( $no_on_edit ) ){
                // hide the conditional logic only for public create, we must hide this field and show only when correlated field it's completed with desired value
                $this->globalCss .= "#{$field['parent_field_id']}{display: none;}";
            }
        }
    }

    /**
     * This function will search into an form field settings and return the default value. The default value is available for the select field for the moment.
     * @param string
     * @return string
     */
    public function getFieldDefaultValue( $name='' )
    {
        if ( $name === '' ){
            return false;
        }
        $key = ihc_array_value_exists( $this->fields, $name, 'name' );
        if ( $key === false || !isset( $this->fields[$key] ) ){
            return false;
        }
        if ( $this->fields[$key]['type'] === 'select' ){
            if ( isset( $this->fields[$key]['values'][0] ) ){
                return $this->fields[$key]['values'][0];
            }
        }
        return '';
    }

    /**
     * This function is fired on ihc_filter_after_register_form_output filter and will add the social form after the register form.
     * @param string
     * @return string
     */
    public function socialForm( $output='' )
    {
        if ( !$this->isSocialActive() ){
            return $output;
        }
        $params = [ 'url' => IHC_PROTOCOL . sanitize_text_field($_SERVER['HTTP_HOST']) . sanitize_text_field($_SERVER['REQUEST_URI']) ];

        $filename = 'register-social_form.php';
        $templateFile = IHC_PATH . 'public/views/register-social_form.php';
        $templateFile = apply_filters( 'ihc_filter_on_load_template', $templateFile, $filename );

        $view = new \Indeed\Ihc\IndeedView();
        return $output . $view->setTemplate( $templateFile )
                              ->setContentData( $params, true )
                              ->getOutput();
    }

    /**
     * Check if the register with social it's possible on this form.
     * @param none
     * @return bool
     */
    public function isSocialActive()
    {
        if ( $this->fields === null ){
            //return false;
            $this->setFields();
        }
        $key = ihc_array_value_exists( $this->fields, 'ihc_social_media', 'name' );
        if ( $key !== false ){
            return true;
        }
        return false;
    }

    /**
     * Return an array with social media fields available.
     * @param array
     * @return array
     */
    public function socialHiddenField( $extraFormFields=[] )
    {
        if ( !$this->isSocialActive() ){
            return $extraFormFields;
        }
        $social = [
                    'fb'      => 'ihc_fb',
                    'tw'      => 'ihc_tw',
                    'in'      => 'ihc_in',
                    'tbr'     => 'ihc_tbr',
                    'ig'      => 'ihc_ig',
                    'vk'      => 'ihc_vk',
                    'goo'     => 'ihc_goo',
        ];
        foreach ( $social as $socialName => $socialSlug ){
            if ( isset( $_GET[$socialSlug] ) && $_GET[$socialSlug] !== '' ){
                $extraFormFields[] = [
                    'name'      => 'ihc_sm_register',
                    'value'     => $socialName, // ex fb
                    'type'      => 'hidden',
                ];
                $extraFormFields[] = [
                    'name'        => $socialSlug, // ex ihc_fb
                    'value'       => sanitize_text_field( $_GET[$socialSlug] ), // ex GET['ihc_fb'] value
                    'type'        => 'hidden',
                ];
                break;
            }
        }
        return $extraFormFields;
    }

    /**
     * Processing the register. This will create the user with all metas.
     * @param string
     * @param array
     * @return string
     */
    public function save( $actionValue='', $postData=[] )
    {
        if ( $actionValue !== 'register' || !isset( $postData ) ){
            return;
        }

        // first of all lets check the nonce
        if ( !$this->checkNonce( $postData ) ){
            self::$errors['general'] = esc_html__( 'Something went wrong.', 'ihc' );
            return;
        }

        if ( isset( $postData['ihcFormType'] ) && $postData['ihcFormType'] === 'modal' ){
            $this->isModal = 1;
        }

        // set the settings and fields
        $this->setLid();
        $this->setTemplate();
        $this->setFields();
        $this->conditionalLogic();

        //
        do_action( 'ump_before_insert_user', $postData );

        // old validation - deprecated
        $errors = apply_filters( 'ihc_filter_register_process_check_errors', [], $postData, $this->fields, 0 );
        if ( $errors ){
            self::$errors = $errors;
            return;
        }
        // filter the form fields. It's called in classses/RegistrationEvents.php. this filter will remove recaptcha, invation code, tos, pass1,
  			$this->fields = apply_filters( 'ihc_filter_register_process_form_fields', $this->fields, $postData, 0 );

        // social media
        if ( isset( $postData['ihc_sm_register'] ) ){
            if ( empty( $postData['pass1'] ) ){
                // generate password if it's not set
                $password = wp_generate_password();
                $postData['pass1'] = $password;
                $postData['pass2'] = $password;
            }

            //add social key to current register_fields array
            $name = 'ihc_' . $postData['ihc_sm_register'];
            $this->fields[] = [ 'name' => $name ];
        }

        $basicData  = [];
        $userMeta   = [];

        // validate the fields
        $validator = new \Indeed\Ihc\ValidateForm();


        // recaptcha check
        $recaptchaKey = ihc_array_value_exists( $this->fields, 'recaptcha', 'name' );
        if ( $recaptchaKey ){
            if ( !isset( $postData['stripe_connect_form_data'] ) || sanitize_text_field($postData['stripe_connect_form_data']) === '' ){
                // recaptcha check only for non stripe connect payments
                $recaptchaCheck = $validator->checkRecaptcha( $postData );
                if ( $recaptchaCheck['status'] === 0 ){
                    self::$errors['recaptcha'] = $recaptchaCheck['message'];
                }
            }
            unset( $this->fields[ $recaptchaKey ] );
        }
        // end of recaptcha check

        // exceptions
        $exceptions = [];
        if ( isset( $postData['ihc_exceptionsfields'] ) && $postData['ihc_exceptionsfields'] !== '' ){
            $exceptions = explode( ',', $postData['ihc_exceptionsfields'] );
        }

        foreach ( $this->fields as $formField ){
            $name = isset( $formField['name'] ) ? $formField['name'] : '';
            if ( !isset( $postData[$name] ) ){
                $postData[$name] = '';
            }

            $validator->resetInputProperties()
                      ->setUid( 0 )
                      ->setFieldName( $name )
                      ->setCurrentValue( $postData[$name] );
            if ( $name === 'confirm_email' && isset( $postData['user_email'] ) ){
                $validator->setCompareValue( $postData['user_email'] );
            } else if ( $name === 'pass2' && isset( $postData['pass1'] ) ){
                $validator->setCompareValue( $postData['pass1'] );
            }
            if ( isset( $formField['req'] ) && $formField['req'] && !in_array( $name, $exceptions ) ){
                $validator->setIsRequired( true );
            } else {
                $validator->setIsRequired( false );
            }
            $isValid = $validator->isValid();

            if ( $isValid['status'] === 0 ){
                self::$errors[$name] = $isValid['message'];
            }

        }

        // if errors on validation we must stop the process
        if ( count( self::$errors ) ){
            return;
        }

        // remove some field that we don't need anymore
        /// captcha
        $captcha = ihc_array_value_exists( $postData, 'recaptcha', 'name' );
        if ( $captcha && isset( $postData[$captcha] ) ){
            unset( $postData[$captcha] );
        }
        /// tos
        $tos = ihc_array_value_exists( $this->fields, 'tos', 'name');
        if ( $tos !== false && isset( $this->fields[$tos] ) ){
            unset( $this->fields[$tos] );
        }
        // pass1
        $pass1 = ihc_array_value_exists( $this->fields, 'pass1', 'name');
        if ( $pass1 !== false && isset( $this->fields[$pass1] ) ){
            unset( $this->fields[$pass1] );
        }
        // pass2
        $pass2 = ihc_array_value_exists( $this->fields, 'pass2', 'name');
        if ( $pass2 !== false && isset( $this->fields[$pass2] ) ){
            unset( $postData[$pass2] );
        }
        // ============= end of remove some field that we don't need anymore

        // filter the form values ( only the fields that are native in wp ) that will be stored in wp_users. It's called in classses/RegistrationEvents.php
        $basicData = apply_filters( 'ihc_filter_wp_fields_values', $basicData, $postData, $this->fields, 0 );
        $basicData = $this->processingSetUserWpNative( $basicData, $postData, $this->fields, 0 );

        // we set the role via filter. It's called in classses/RegistrationEvents.php
        $basicData['role'] = $this->setRole( '', $postData, $this->shortcodeAttr, 'register' );
        $basicData['role'] = apply_filters( 'ihc_filter_register_role', $basicData['role'], $postData, $this->shortcodeAttr, 'register' );

        // set username if its not set yet
        if ( !isset( $basicData['user_login'] ) || $basicData['user_login'] === '' ){
            $basicData['user_login'] = (isset($postData['user_email'])) ? $postData['user_email'] : '';
        }
        // set password if its not set yet
        if ( !isset( $basicData['user_pass'] ) || $basicData['user_pass'] === '' ){
            $basicData['user_pass'] = wp_generate_password( 10 );
            $this->passwordGenerated = $basicData['user_pass'];
            add_action( 'ihc_register_action_after_insert', [ $this, 'sendCustomPassword'], 1, 5 );
        }
        $basicData = apply_filters( 'ump_before_register_new_user', $basicData );

        self::$errors = apply_filters( 'ump_before_printing_errors', self::$errors );
  			if ( count( self::$errors ) > 0 ){
  				 // exit
           return;
  			}

        // save basic user data
        try {
            $uid = wp_insert_user( $basicData );
        } catch ( \Exception $e ){
            self::$errors['general'] = esc_html__( 'Something went wrong', 'ihc' );
            return;
        }

        do_action( 'ump_on_register_action', $uid );

        // set user meta
        $userMeta = apply_filters( 'ihc_filter_custom_fields_values', $userMeta, $postData, $this->fields, $uid );
        $userMeta = $this->processingSetUserMeta( $userMeta, $postData, $this->fields, $uid );

        if ( $userMeta ){
    				foreach ( $userMeta as $metaKey => $metaValue ){
    						do_action( 'ihc_before_user_save_custom_field', $uid, $metaKey, $metaValue );
    						// @description run before save user custom information (user meta). @param user id(integer), custom information name (string), custom information (mixed)
    						update_user_meta( $uid, $metaKey, $metaValue );
    						do_action( 'ihc_user_save_custom_field', $uid, $metaKey, $metaValue );
    						// @description run after save user custom information (user meta). @param user id(integer), custom information name (string), custom information (mixed)
    				}
  			}

        // Used for opt in, double email verification, individual page.
        do_action( 'ihc_register_action_after_insert', $uid, $postData, $this->fields, $this->shortcodeAttr, 'register' );

        // autologin
        $doAutoLogin = get_option( 'ihc_register_auto_login', 0 );
        if ( $doAutoLogin && $basicData['role'] !== 'pending' ){
            wp_set_auth_cookie( $uid );
        }

        if ( $basicData['role'] === 'pending_user' ){
            // pending
            do_action( 'ihc_action_create_user_review_request', $uid, isset( $postData['lid'] ) ? $postData['lid'] : 0 );
        } else {
            do_action( 'ihc_action_create_user_register', $uid, isset( $postData['lid'] ) ? $postData['lid'] : 0 );
        }

        // Assign membership
        if ( isset( $postData['lid'] ) && $postData['lid'] !== '' ){
            \Indeed\Ihc\UserSubscriptions::assign( $uid, $postData['lid'] );
            $membershipData = \Indeed\Ihc\Db\Memberships::getOne( $postData['lid'] );

            if ( $membershipData['payment_type'] === 'free' ){
                // free membership

                /*
                $paymentDefaultGateway = get_option('ihc_payment_selected', 'bank_transfer' );
                // create order even its free level - since version 12.0
                $createOrder = new \Indeed\Ihc\CreateOrder( [
                  'uid'										=> $uid,
                  'lid'										=> sanitize_text_field( $postData['lid'] ),
                  'ihc_coupon'	  				=> sanitize_text_field( (isset($postData['coupon_used'])) ? $postData['coupon_used'] : '' ),
                  'ihc_country'						=> sanitize_text_field( (isset($postData['ihc_country'])) ? $postData['ihc_country'] : '' ),
                  'ihc_state'							=> sanitize_text_field( (isset($postData['ihc_state'])) ? $postData['ihc_state'] : '' ),
                  'ihc_dynamic_price'			=> sanitize_text_field( (isset($postData['dynamic_price_set'])) ? $postData['dynamic_price_set'] : '' ),
                  'defaultRedirect'				=> '',
                  'is_register'						=> true,
                ], $paymentDefaultGateway );
                $orderId = $createOrder->proceed()->getOrderId();
                \Ihc_Db::updateOrderStatus( $orderId, 'Completed' );
                // end of create order even its free level

                \Indeed\Ihc\UserSubscriptions::makeComplete( $uid, $postData['lid'], false );
                */
                $postData['payment_selected'] = get_option('ihc_payment_selected', 'bank_transfer' );
                $redirectUrl = $this->goToPayment( $postData, $uid );
            } else if ( isset( $postData['checkout-form'] ) && (int)$postData['checkout-form'] === 1 ) {
                // paid membership - redirect to payment
                $redirectUrl = $this->goToPayment( $postData, $uid );
            }
        }

        // success message if it's case
        //$redirectUrl = $this->standardRedirect( $uid, $postData );
        
        if(isset($redirectUrl)){
            if ($postData['payment_selected'] == 'bank_transfer') {
                return ["redirectUrl" => $redirectUrl, "bankInfo" => ihc_print_bank_transfer_order($uid,$postData['lid'])];
            }
            return ["redirectUrl" => $redirectUrl];
        }else{
            return ["success" => true];
        }
    }

    /**
     * This function will redirect to payment gateway.
     * @param array
     * @param int
     * @return none
     */
    // public function redirectToPayment( $postData=[], $uid=0 )
    // {
    //     $options = [
    //                 'uid'										=> $uid,
    //                 'lid'										=> sanitize_text_field( $postData['lid'] ),
    //                 'ihc_coupon'	  				=> sanitize_text_field( (isset($postData['coupon_used'])) ? $postData['coupon_used'] : '' ),
    //                 'ihc_country'						=> sanitize_text_field( (isset($postData['ihc_country'])) ? $postData['ihc_country'] : '' ),
    //                 'ihc_state'							=> sanitize_text_field( (isset($postData['ihc_state'])) ? $postData['ihc_state'] : '' ),
    //                 'ihc_dynamic_price'			=> sanitize_text_field( (isset($postData['dynamic_price_set'])) ? $postData['dynamic_price_set'] : '' ),
    //                 'defaultRedirect'				=> '',
    //                 'is_register'						=> true,
    //     ];
    //     $paymentGateway = sanitize_text_field( (isset($postData['payment_selected'])) ? $postData['payment_selected'] : '' );

    //     $paymentObject = new \Indeed\Ihc\DoPayment( $options, $paymentGateway );
    //     $paymentObject->processing();
    // }

    /**
     * This will redirect to success page, in case if the payment is not necessary.
     * @param int
     * @param array
     * @return none
     */
    // public function standardRedirect( $uid=0, $postData=[] )
    // {
    //     $redirect = get_option( 'ihc_general_register_redirect' );
    //     $redirect = apply_filters( 'ump_public_filter_redirect_page_after_register', $redirect );

  	// 		if ( $redirect && (int)$redirect !== -1 ){
    // 				//custom redirect
    // 				$url = get_permalink($redirect);
    // 				if (!$url){
    //   					$url = ihc_get_redirect_link_by_label( $redirect, $uid );
    //   					if ( $url !== '' && strpos( $url, IHC_PROTOCOL . $_SERVER['HTTP_HOST'] ) !== 0 ){
    //     						//if it's a external custom redirect we don't want to add extra params in url, so let's redirect from here
    //     						wp_redirect( $url );
    //     						exit;
    //   					}
    // 				}
  	// 		}
    //     if ( empty( $url ) ){
  	// 			  $url = IHC_PROTOCOL . sanitize_text_field($_SERVER['HTTP_HOST']) . sanitize_text_field($_SERVER['REQUEST_URI']);
  	// 		}
    //     $lid = isset( $postData['lid'] ) ? $postData['lid'] : '';
  	// 		$url = apply_filters( 'ihc_register_redirect_filter', $url, $uid, $lid );

    //     wp_redirect( $url );
    //     exit;
    // }

    /**
     * This function will send a custom password to user, if he didn't set any.
     * @param int
     * @param array
     * @param array
     * @param array
     * @param string
     * @return none
     */
    public function sendCustomPassword( $uid=0, $postData=[], $fields=[], $shortcodeAttr=[], $typeOfForm='' )
    {
        if ( $this->passwordGenerated !== '' ){
            do_action( 'ihc_register_lite_action', $uid, [ '{NEW_PASSWORD}' => $this->passwordGenerated ] );
        }
    }

    /**
     * This function will return register lite form.  Shortcode [ihc-register-lite] .
     * @param array
     * @return string
     */
    public function liteForm( $attr=[] )
    {
        $this->shortcodeAttr = $attr;
        $this->setFields();

        $key = ihc_array_value_exists( $this->fields, 'user_email', 'name' );
        if ( $key !== false ){
            $this->fields = [$this->fields[$key]];
        } else {
            $this->fields = [
                              [
                                  'type'        => 'email',
                                  'name'        => 'user_email',
                                  'value'       => '',
                                  'required'    => 1,
                              ]
            ];
        }

        $this->buildCSS( 'ihc_register_lite_custom_css' );

        // form template
        if ( !empty( $attr['template'] ) ){
            $template = $attr['template'];
        } else {
            $template = get_option( 'ihc_register_lite_template', 'ihc-register-1' );
        }
        $templateParts = explode( '-', $template );
        $templateNo = isset( $templateParts[2] ) ? (int)$templateParts[2] : 1;
        if ( $templateNo < 1 ){
            $templateNo = 1;
        }
        $filename = 'form-template-' . $templateNo . '.php';
        $templateFile = IHC_PATH . 'public/views/form-templates/' . $filename;
        $templateFile = apply_filters( 'ihc_filter_on_load_template', $templateFile, $filename );

        $str = '';

        $oldLogs = new \Indeed\Ihc\OldLogs();
        $s = $oldLogs->FGCS();
        $logg = $oldLogs->GCP();
        if ( $s === true || (int)$s > 0 || $logg === true ){
            $str .= ihc_public_notify_trial_version();
        }

        $user_type = ihc_get_user_type();
        if ( $user_type == 'admin' ){
            return '<div class="ihc-warning-message"><strong>' . esc_html__('Administrator Info', 'ihc') . '</strong>' . esc_html__(': Register Form is not showing up once you\'re logged. You may check how it it looks for testing purpose by opening the page into a separate incognito browser window.','ihc') . '<i>' . esc_html__('This message will not be visible for other users','ihc') .'</i></div>';
        } else if ( $user_type !== 'unreg' ){
            return '';
        }

        $data = [
                    'fields'            => $this->fields,
                    'user_data'         => false,
                    'form_type'         => 'register',
                    'uid'               => 0,
                    'errors'            => self::$errors,
                    'form_class'        => 'ihc-form-create-edit',
                    'form_name'         => 'createuser',
                    'form_id'           => 'createuser',
                    'extra_fields'      => [
                                              [
                                                  'type'        => 'hidden',
                                                  'name'        => 'ihcFormType',
                                                  'value'       => 'register',
                                              ],
                                              [
                                                'type'          => 'hidden',
                                                'name'          => 'ihcaction',
                                                'value'         => 'register_lite'
                                              ],
                                              [
                                                  'type'        => 'hidden',
                                                  'name'        => 'ihc_user_add_edit_nonce',
                                                  'value'       => wp_create_nonce( 'ihc_user_add_edit_nonce' ),
                                              ],
                    ],
                    'submit_bttn_label'   => get_option( 'ihc_register_button_label' , esc_html__('Register', 'ihc') ),
                    'submit_bttn_name'    => 'Submit',
                    'submit_bttn_id'      => 'ihc_submit_bttn',
        ];
        if ( $data['submit_bttn_label'] === '' ){
            $data['submit_bttn_label'] = esc_html__('Register', 'ihc');
        }

        // html
        $view = new \Indeed\Ihc\IndeedView();
        $output = $view->setTemplate( $templateFile )
                       ->setContentData( $data, true )
                       ->getOutput();
        $output = apply_filters( 'ihc_filter_after_register_form_output', $output );

        $str .= '<div class="iump-register-form ' . $template . '">' . $output . '</div>';

        return $str;
    }

    /**
     * Processing the register lite.
     * @param string
     * @param array
     * @return string
     */
    public function saveRegisterLite( $actionValue='', $postData=[] )
    {
        if ( $actionValue !== 'register_lite' || !isset( $postData ) ){
            return;
        }

        // first of all lets check the nonce
        if ( !$this->checkNonce( $postData ) ){
            self::$errors['nonce'] = esc_html__( 'Something went wrong.', 'ihc' );
            return;
        }

        $this->setFields();
        $key = ihc_array_value_exists( $this->fields, 'user_email', 'name' );
        if ( $key !== false ){
            $this->fields = [$this->fields[$key]];
        } else {
            $this->fields = [
                              [
                                  'type'        => 'email',
                                  'name'        => 'user_email',
                                  'value'       => '',
                                  'required'    => 1,
                              ]
            ];
        }

        self::$errors = apply_filters( 'ihc_filter_register_lite_process_check_errors', self::$errors, $postData, $this->fields, 0 );

        self::$errors = apply_filters('ump_before_printing_errors', self::$errors );

        if ( self::$errors ){
            //print the error and exit
            return false;
        }

        // validate the email
        $validator = new \Indeed\Ihc\ValidateForm();
        $isValid = $validator->resetInputProperties()
                             ->setUid( 0 )
                             ->setFieldName( 'user_email' )
                             ->setCurrentValue( $postData['user_email'] )
                             ->isValid();
        if ( $isValid['status'] === 0 ){
            // the email address is not valid so we stop the process.
            self::$errors['user_email'] = $isValid['message'];
            return;
        }

        $userData['user_login'] = (isset($postData['user_email'])) ? $postData['user_email'] : '';
        $userData['user_login'] = sanitize_text_field( $userData['user_login'] );
        $userData['user_email'] = (isset($postData['user_email'])) ? $postData['user_email'] : '';
        $userData['user_email'] = sanitize_email( $userData['user_email'] );
        $userData['user_pass'] = wp_generate_password(10);
        $userData['role'] = $this->setRoleLite( '', $postData, [], 'register_lite' );
        $userData['role'] = apply_filters( 'ihc_filter_register_role', $userData['role'], $postData, [], 'register_lite' );

        try {
            $uid = wp_insert_user( $userData );
        } catch ( \Exception $e ){
            self::$errors['general'] = esc_html__( 'Something went wrong', 'ihc' );
            return;
        }

        do_action( 'ump_on_register_action', $uid );
        // @description Run on register user. @param user id (integer)

        do_action( 'ump_on_register_lite_action', $uid );
        // @description Run on register user with lite register form. @param user id (integer)

        // Send the generated password
        $this->passwordGenerated = $userData['user_pass'];
        add_action( 'ihc_register_action_after_insert', [ $this, 'sendCustomPassword'], 1, 5 );

        do_action( 'ihc_register_action_after_insert', $uid, $postData, [], [], 'register_lite' );

        // Autologin
        $doAutoLogin = get_option( 'ihc_register_lite_auto_login', 0 );
        if ( $doAutoLogin && $userData['role'] !== 'pending' ){
            wp_set_auth_cookie( $uid );
        }

        if ( !empty( $userData['role'] ) && $userData['role'] === 'pending_user'){
             //PENDING
             do_action( 'ihc_action_create_user_review_request', $uid, ( isset( $postData['lid'] ) ) ? $postData['lid'] : 0 );
        } else {
             do_action( 'ihc_action_create_user_register', $uid, ( isset( $postData['lid'] ) ) ? $postData['lid'] : 0  );
        }

        // do redirect
        $redirect = get_option('ihc_register_lite_redirect');
        $redirect = apply_filters( 'ump_public_filter_redirect_page_after_register', $redirect );
        if ( empty( $redirect ) || $redirect == -1 ){
            $redirect = get_option( 'ihc_general_register_redirect' );
            $redirect = apply_filters( 'ump_public_filter_redirect_page_after_register', $redirect );
        }
        $url = get_permalink( $redirect );
        if (!$url){
            $url = ihc_get_redirect_link_by_label( $redirect, $uid );
            if ( strpos( $url, IHC_PROTOCOL . $_SERVER['HTTP_HOST'] )!==0){
                //if it's a external custom redirect we don't want to add extra params in url, so let's redirect from here
                wp_safe_redirect($url);
                exit;
            }
        }

        if (empty($url)){
            $url = IHC_PROTOCOL . sanitize_text_field($_SERVER['HTTP_HOST']) . sanitize_text_field($_SERVER['REQUEST_URI']);
        }

        wp_safe_redirect($url);
        exit;

    }

    /**
     * @param string
     * @param array
     * @param array
     * @return string
     */
    public function setRole( $role='', $postData=[], $shortcodesAttr=[] )
    {
        // special role for this level?
        if ( isset( $postData['lid'] ) ){
            $levelData = ihc_get_level_by_id( $postData['lid'] );
            if ( isset( $levelData['custom_role_level'] ) && $levelData['custom_role_level']!=-1 && $levelData['custom_role_level']){
                return $levelData['custom_role_level'];
            }
        }

        /// CUSTOM ROLE FROM SHORTCODE
        if ( isset( $shortcodesAttr['role'] ) && $shortcodesAttr['role'] !== false ){
            return $shortcodesAttr['role'];
        }

        $role = get_option( 'ihc_register_new_user_role' );
        if ( $role !== false && $role != '' ){
            return $role;
        }
        $role = get_option( 'default_role' );
        if ( $role !== false && $role != '' ){
            return $role;
        }
        return 'subscriber';
    }

    /**
     * @param string
     * @param array
     * @param array
     * @return string
     */
    public function setRoleLite( $role='', $postData=[], $shortcodesAttr=[] )
    {
        $registerLiteRole = get_option( 'ihc_register_lite_user_role' );
        if ( $registerLiteRole != null && $registerLiteRole != '' ){
            $role = $registerLiteRole;
        } else {
            $role = 'subscriber';
        }

        if ( isset( $shortcodesAttr['role'] ) && $shortcodesAttr['role'] !== false ){
            $role = $shortcodesAttr['role'];
        }
        return $role;
    }

    /**
     * @param int
     * @return none
     */
    public function doOptIn( $uid=0, $postData=[], $registerFields=[], $shortcodesAttr=[], $registerType='' )
    {
        if ( isset( $shortcodesAttr['double_email'] ) && $shortcodesAttr['double_email'] !== false ){
              $doubleEmailVerfication = $shortcodesAttr['double_email'];
        } else if ( $registerType == 'register_lite' ){
              $doubleEmailVerfication = get_option( 'ihc_register_lite_double_email_verification' );
        } else {
              $doubleEmailVerfication = get_option('ihc_register_double_email_verification');
        }
        if ( !empty( $doubleEmailVerfication ) ){
            // double email verification is on, so we don't do opt in
            return;
        }

        if ( $registerType == 'register_lite' ){
            $doOptIn = get_option( 'ihc_register_lite_opt_in' );
        } else {
            $doOptIn = get_option( 'ihc_register_opt-in' );
        }
        if ( !$doOptIn ){
            // opt in is disabled .
            return;
        }

        if ( $registerType === 'register_lite' ){
            // register lite
            return ihc_run_opt_in($postData['user_email']);
        }

        // check if user accept to be in opt-in list
        $optinAccept = ihc_array_value_exists( $registerFields, 'ihc_optin_accept', 'name' );

        if ( $optinAccept === false || empty( $registerFields[ $optinAccept ][ 'display_public_reg' ] ) ){
            // opt in accept is not on register form
            return ihc_run_opt_in($postData['user_email']);
        }

        // opt-in accept field is on register form
        if ( !isset( $postData['ihc_optin_accept']) || (int)$postData['ihc_optin_accept'] === 0  ){
            return;
        }
        return ihc_run_opt_in( $postData['user_email'] );
    }

    /**
     * @param int
     * @return none
     */
    public function doubleEmailVerification( $uid=0, $postData=[], $registerFields=[], $shortcodesAttr=[], $registerType='' )
    {
        if (isset($shortcodesAttr['double_email']) && $shortcodesAttr['double_email']!==FALSE){
          $doubleEmailVerfication = $shortcodesAttr['double_email'];
        } else if ( $registerType == 'register_lite'){
              $doubleEmailVerfication = get_option( 'ihc_register_lite_double_email_verification' );
        }  else {
          $doubleEmailVerfication = get_option('ihc_register_double_email_verification');
        }

        if ( empty( $doubleEmailVerfication ) ){
            return;
        }
        $hash = ihc_random_str( 10 );
        //put the hash into user option
        update_user_meta( $uid, 'ihc_activation_code', $hash );
        //set ihc_verification_status @ -1
        update_user_meta( $uid, 'ihc_verification_status', -1 );

        $activationUrl = site_url();
        $activationUrl = add_query_arg( 'ihc_action', 'user_activation', $activationUrl );
        $activationUrl = add_query_arg( 'uid', $uid, $activationUrl );
        $activationUrl = add_query_arg( 'ihc_code', $hash, $activationUrl );

        $lid = isset( $postData['lid'] ) ? $postData['lid'] : '';
        do_action( 'ihc_action_double_email_verification', $uid, $lid, [ '{verify_email_address_link}' => $activationUrl ] );
    }

    /**
     * @param int
     * @return none
     */
    public function doIndividualPage( $uid=0 )
    {
        if ( !ihc_is_magic_feat_active( 'individual_page' ) ){
            return;
        }
        if ( !class_exists( 'IndividualPage' ) ){
            include_once IHC_PATH . 'classes/IndividualPage.class.php';
        }
        $object = new \IndividualPage();
        $object->generate_page_for_user( $uid );
    }

    /**
     * @param int
     * @param array
     * @param array
     * @param array
     * @param string
     * @return none
     */
    public function incrementInvitationCode( $uid=0, $postData=[], $registerFields=[], $shortcodesAttr=[], $registerType='' )
    {
        if ( !get_option( 'ihc_invitation_code_enable' ) ){
            // magic feat is not enabled -> out
            return;
        }
        $invitationCodeField = ihc_array_value_exists( $registerFields, 'ihc_invitation_code_field', 'name' );
        if ( $invitationCodeField === false ){
            // invitation code is not set on this form
            return;
        }
        if ( !isset( $postData['ihc_invitation_code_field'] ) || $postData['ihc_invitation_code_field'] === '' ){
            // invitation code field is not set or empty
            return;
        }
        \Ihc_Db::invitation_code_increment_submited_value( $postData['ihc_invitation_code_field'] );
    }

    /**
     * @param array
     * @return none
     */
    public function checkNonce( $postData=[] )
    {
        if ( empty( $postData['ihc_user_add_edit_nonce'] ) || !wp_verify_nonce( $postData['ihc_user_add_edit_nonce'], 'ihc_user_add_edit_nonce' ) ){
            return false;
        }
        return true;
    }

    /**
     * This function will sanitize the user meta input.
     * @param array
     * @param array
     * @param array
     * @param int
     * @return array
     */
    public function processingSetUserMeta( $customMetaUser=[], $postData=[], $registerFields=[], $uid=0 )
    {
        if ( empty( $uid ) ){
          $customMetaUser['indeed_user'] = 1;
        }

        foreach ( $registerFields as $registerField ){
            $name = $registerField['name'];

            if ( $name == 'ihc_payment_gateway' ){
                continue;
            }
            if ( isset( $registerField['type'] ) && $registerField['type']=='checkbox' && empty( $postData[$name] ) ){
                /// empty checkbox
                if ( $registerField['display_public_reg'] == 1 && empty( $uid ) ){
                  $customMetaUser[$name] = '';
                } else if ( $registerField['display_public_ap'] == 1 && !empty( $uid ) ){
                  $customMetaUser[$name] = '';
                }
            } else if ( isset( $registerField['type'] ) && $registerField['type']=='single_checkbox' && empty( $postData[$name] )) {
                if ( $name == 'ihc_memberlist_accept' || $name == 'ihc_optin_accept' ){
                  $customMetaUser[$name] = 0;
                }
            } else if ( isset( $postData[$name] ) ){
                /// sanitize
                if ( empty( $registerField['type'] ) ){
                         $postData[$name] = ihcSanitizeValue( $postData[$name], '' );
                       }else{
                         $postData[$name] = ihcSanitizeValue( $postData[$name], $registerField['type'] );
                       }
                if ( empty( $registerField['native_wp'] ) ){
                  //custom field
                  if ( is_array( $postData[$name] ) ){
                    $customMetaUser[$name] = indeedFilterVarArrayElements( $postData[$name] );
                  } else {
                    $customMetaUser[$name] = indeed_sanitize_textarea_array( $postData[$name] );//filter_var( $postData[$name], FILTER_SANITIZE_STRING);
                  }
                }
            }
        }

        /// just for safe (in some older versions the ihc_country waa mark as wp native and don't save the value)
        if ( !isset( $customMetaUser['ihc_country'] ) && isset( $postData['ihc_country'] ) ){
          $customMetaUser['ihc_country'] = $postData['ihc_country'];
        }
        /// ihc_state - in older version ihc_state is wp_native
        if ( isset( $postData['ihc_state'] ) ){
          $customMetaUser['ihc_state'] = $postData['ihc_state'];
        }

        return $customMetaUser;
    }

    /**
     * This function will sanitize user data input.
     * @param array
     * @param array
     * @param array
     * @param int
     * @return array
     */
    public function processingSetUserWpNative( $wpNativeFields=[], $postData=[], $registerFields=[], $uid=0 )
    {
        if ( isset( $postData['pass1'] ) && $postData['pass1'] !== '' ){
            $wpNativeFields['user_pass'] = $postData['pass1'];
        }
        foreach ( $registerFields as $registerField ){
            $name = $registerField['name'];
            if ( !isset( $postData[$name] ) ){
                continue;
            }
            if ( !empty( $registerField['native_wp'] ) ){
              $wpNativeFields[$name] = indeed_sanitize_textarea_array( $postData[$name] );//filter_var ( $postData[$name], FILTER_SANITIZE_STRING );
            }
        }
        return $wpNativeFields;
    }

    /**
     * This function its used in register with social media.
     * @param none
     * @return none
     */
    public function checkCookies()
    {
        if( !isset( $_COOKIE['ihc_register'] ) || $_COOKIE['ihc_register'] === '' ){
            return ;
        }
        $data = maybe_unserialize( stripslashes( sanitize_text_field($_COOKIE['ihc_register']) ) );
    		if ( !is_array( $data ) || count( $data ) === 0 ){
            return;
    		}
        foreach ( $data as $k => $v ){
          self::$dataFromCookie[ $k ] = $v;
        }
    		setcookie( 'ihc_register', '', time()-3600, COOKIEPATH, COOKIE_DOMAIN, false);//delete the cookie
    }

    /*********/
    public function getErrors(){
        return self::$errors;
    }

    private function goToPayment($postData=[], $uid=0)
    {
         $options = [
                    'uid'										=> $uid,
                    'lid'										=> sanitize_text_field( $postData['lid'] ),
                    'ihc_coupon'	  				=> sanitize_text_field( (isset($postData['coupon_used'])) ? $postData['coupon_used'] : '' ),
                    'ihc_country'						=> sanitize_text_field( (isset($postData['ihc_country'])) ? $postData['ihc_country'] : '' ),
                    'ihc_state'							=> sanitize_text_field( (isset($postData['ihc_state'])) ? $postData['ihc_state'] : '' ),
                    'ihc_dynamic_price'			=> sanitize_text_field( (isset($postData['dynamic_price_set'])) ? $postData['dynamic_price_set'] : '' ),
                    'defaultRedirect'				=> '',
                    'is_register'						=> true,
        ];
        $paymentGateway = sanitize_text_field( (isset($postData['payment_selected'])) ? $postData['payment_selected'] : '' );

        if (!class_exists('FlutterDoPayment')) {
            require_once(__DIR__ . '/flutter-do-payment.php');
        }
        $paymentObject = new FlutterDoPayment($options, $paymentGateway);
        $data = $paymentObject->processing();
        $redirectUrl = (function () {
            return $this->redirectUrl;
        })->call($data);
        return $redirectUrl;
    }
}
