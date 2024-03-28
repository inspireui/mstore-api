<?php
require_once(dirname(__DIR__) . '/flutter-base.php');

class FlutterMembership extends FlutterBaseController
{

    protected $namespace = 'api/flutter_membership';

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_membership_routes'));
    }

    public function register_membership_routes()
    {
        register_rest_route($this->namespace, '/plans', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_plans'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));

        register_rest_route($this->namespace, '/register', array(
            array(
                'methods' => 'POST',
                'callback' => array($this, 'membership_register'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));

        register_rest_route($this->namespace, '/payments', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_payments'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));
    }

    public function get_plans()
    {
        if (is_plugin_active('indeed-membership-pro/indeed-membership-pro.php')) {
            $levels = \Indeed\Ihc\Db\Memberships::getAll();
            return array_values($levels);
        } else {
            return parent::sendError("plugin_not_found", "Please install Membership Pro Ultimate WP", 404);
        }
    }

    public function get_payments()
    {
        if (is_plugin_active('indeed-membership-pro/indeed-membership-pro.php')) {
            $payments = ihc_get_active_payments_services();
            return $payments;
        } else {
            return parent::sendError("plugin_not_found", "Please install Membership Pro Ultimate WP", 404);
        }
    }

    public function membership_register()
    {
        if (is_plugin_active('indeed-membership-pro/indeed-membership-pro.php')) {
            if (!class_exists('FlutterRegisterForm')) {
                require_once(__DIR__ . '/flutter-register-form.php');
            }
            $nonce = wp_create_nonce('ihc_user_add_edit_nonce');
            $json = file_get_contents('php://input');
            $_POST = json_decode($json, TRUE);
            $actionValue = 'register';
            $postData = [
                'user_login' => $_POST['user_login'],
                'user_email' => $_POST['user_email'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'pass1' => $_POST['pass1'],
                'pass2' => $_POST['pass2'],
                'ihc_country' => $_POST['ihc_country'] ?? 'US',
                'ihc_avatar' => $_POST['ihc_avatar'] ?? '',
                'tos' => $_POST['tos'],
                // 'code' => $_POST['code'],
                // 'csrf' => $_POST['csrf'],
                // 'digit_otp' => $_POST['digit_otp'],
                'ihcFormType' => $_POST['ihcFormType'],
                'ihcaction' => $_POST['ihcaction'],
                'ihc_user_add_edit_nonce' => $nonce,
                'lid' => $_POST['lid'],
                'payment_selected' => $_POST['ihc_payment_gateway'],
                'checkout-form' => 1
            ];
            $obj = new FlutterRegisterForm();
            $res = $obj->save($actionValue, $postData);
            $errors = $obj->getErrors();
            if(count($errors)){
                $keys = array_keys($errors);
                return parent::sendError($keys[0], $errors[$keys[0]], 400);
            }
            return $res;
        } else {
            return parent::sendError("plugin_not_found", "Please install Membership Pro Ultimate WP", 404);
        }

    }
}

new FlutterMembership;