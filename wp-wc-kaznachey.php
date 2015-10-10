<?php
/*
Plugin Name: Kaznachey for WooCommerce
Plugin URI: http://www.kaznachey.ua
Description: Кредитная карта Visa/MC, Webmoney, Liqpay, Qiwi... (www.kaznachey.ua)
Version: 2.3.1
Author: §aXuM
Author URI: http://www.kaznachey.ua/
*/

add_action("init", "wp_wc_kaznachey_init");
function wp_wc_kaznachey_init(){
    load_plugin_textdomain("wp_wc_kaznachey", false, basename(dirname(__FILE__)));
}

add_action( 'plugins_loaded', 'init_wc_kaznachey_Payment_Gateway' );
function init_wc_kaznachey_Payment_Gateway() {
	if (!class_exists('WC_Payment_Gateway'))
		return; // if the WC payment gateway class is not available, do nothing
    /**
     * Класс для работы с методом оплаты kaznachey для WooCommerce.
     * Смотри также наследуемый абстрактный класс WC_Payment_Gateway (есть комменты перед заготовками методов)
     */
    class WC_kaznachey_Payment_Gateway extends WC_Payment_Gateway{
		
		public function kaznachey_init() {
			$this->merchantGuid = $this->get_option('merchantGuid');
            $this->merchnatSecretKey = $this->get_option('merchnatSecretKey');
		}
		
        public function __construct(){
            $this->id = 'kaznachey';
			$this->paymentKaznacheyUrl = "http://payment.kaznachey.net/api/PaymentInterface/";

            $this->has_fields = false;
            $this->method_title = 'kaznachey';
			
			$cc_types = $this->GetMerchnatInfo();
			if(isset($cc_types["PaySystems"])){
				$box = '<br><br><label for="cc_types">Выберите способ оплаты</label><select name="cc_types" id="cc_types">';
				foreach ($cc_types["PaySystems"] as $paysystem){
					$box .= "<option value='$paysystem[Id]'>$paysystem[PaySystemName]</option>";
				}
				$box .= '</select><br><input type="checkbox" checked="checked" value="1" name="cc_agreed" id="cc_agreed"><label for="cc_agreed"><a href="'.$merchnatInfo['TermToUse'].'" target="_blank">Согласен с условиями использования</a></label>';
				
				$box .= "<script type=\"text/javascript\">
				(function(){ 
				var cc_a = jQuery('#cc_agreed');
					 cc_a.on('click', function(){
						if(cc_a.is(':checked')){	
							jQuery('.custom_gateway').find('.error').text('');
						}else{
							cc_a.next().after('<span class=\"error\">Примите условие!</span>');
						}
					 });
					jQuery('body').on('click', function() {
						 document.cookie='cc_types='+jQuery('#cc_types').val();
					});	
				})(); 
				</script> ";
			}
			
			$this->method_description = __( 'Payment by <a href="http://www.kaznachey.ua/" title="kaznachey is a full service of your website in the field of organization and receiving electronic payments." target="_blank">kaznachey</a>.', 'wp_wc_kaznachey' );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description').$box;
            $this->pay_mode = $this->get_option('pay_mode');
            $this->icon_type = $this->get_option('icon_type');
            if($this->icon_type)
                $this->icon = apply_filters('woocommerce_kaznachey_icon', plugin_dir_url(__FILE__) . 'kaznachey_' . $this->icon_type . '.png');

            // хук для сохранения опций, доступных в настройках метода оплаты в админке (опр. в ф-ции init_form_fields)
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            // хук для отрисовки формы перед переходом на мерчант (см. ф-цию receipt_page)
            add_action( 'woocommerce_receipt_kaznachey', array( $this, 'receipt_page' ) );
            // хук для обработки Result URL
            add_action( 'woocommerce_api_wc_kaznachey_payment_gateway', array( $this, 'kaznachey_result' ) );
        }

        /**
         * Метод определяет, какие поля будут доступны в настройках метода оплаты в админке.
         * Описание API см. здесь - http://docs.woothemes.com/document/settings-api/
         * @return string|void
         */
        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'wp_wc_kaznachey' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable', 'wp_wc_kaznachey' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'wp_wc_kaznachey' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'wp_wc_kaznachey' ),
                    'default' => 'kaznachey',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __( 'Description', 'wp_wc_kaznachey' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'wp_wc_kaznachey' ),
                    'default' => __( 'Кредитная карта Visa/MC, Webmoney, Liqpay, Qiwi... (www.kaznachey.ua)' ),
                ),
                'merchantGuid' => array(
                    'title' => __( 'Merchant ID', 'wp_wc_kaznachey' ),
                    'type' => 'text',
                    'description' => __( 'Unique id of the store in kaznachey system. You can find it in your <a href="http://kaznachey.ua" target="_blank">shop control panel</a>.', 'wp_wc_kaznachey' ),
                ),
                'merchnatSecretKey' => array(
                    'title' => __( 'Secret key', 'wp_wc_kaznachey' ),
                    'type' => 'text',
                    'description' => __( 'Custom character set is used to sign messages are forwarded.', 'wp_wc_kaznachey' ),
                ), 
                'icon_type' => array(
                    'title' => __( 'Image', 'wp_wc_kaznachey' ),
                    'description' =>  __( '(optional) kaznachey icon which the user sees during checkout on payment selection page.', 'wp_wc_kaznachey' ),
                    'type' => 'select',
                    'options' => array(
                        '' => __( "Don't use", 'wp_wc_kaznachey' ),
                        'transp' => __( 'Transparent', 'wp_wc_kaznachey' ),
                    ),
                ),
            );
        }

        /**
         * Метод обрабатывает событие "Размещения заказа".
         * Переводит покупателя на страницу, где формируется форма для перехода на мерчант.
         * @param $order_id номер заказа
         * @return array|void
         */
        public function process_payment( $order_id ){
            global $woocommerce;
            $order = new WC_Order( $order_id );
            return array(
                'result' => 'success',
                'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

    public function receipt_page($order_id){
		global $woocommerce;
		$order = new WC_Order( $order_id );
		$lang = get_locale();
		switch($lang){
			case 'en_EN':
				$lang = 'EN';
				break;
			case 'ru_RU':
				$lang = 'RU';
				break;
			default:
				$lang = 'RU';
				break;
		}
	$amount = number_format($order->order_total, 2, '.', '');
	$currency = get_woocommerce_currency();
	$available_currencies = array('BYR', 'EUR', 'RUB', 'UAH', 'USD', 'UZS');
	if($currency == 'RUR')
		$currency = 'RUB';
	if(!in_array($currency, $available_currencies))
		$currency = 'USD';
	$desc = 'Оплата заказа №' . $order_id;
	$success_url = $this->get_return_url($order).'&status=success';
	$result_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', __CLASS__, home_url( '/' ) ) ).'&status=done';

	$sum=$qty=0;
	$products_items = $order->get_items();	
  	foreach ($products_items as $key=>$item)
	{
		$thumb = wp_get_attachment_image_src( get_post_thumbnail_id($item['product_id']), 'large' );
		$request['Products'][] = array(
			'ProductId'=>$item['product_id'],
			'ImageUrl'=>(isset($thumb[0]))?$thumb[0]:'',
			'ProductItemsNum'=>$item['qty'],
			'ProductName'=>$item['name'],
			'ProductPrice'=>($item['line_total'])/$item['qty'],
		);
		$sum += $item['line_total'];
		$qty += $item['qty'];
	}
	
	if($amount != $sum){
		$tt = $amount - $sum; 
		$request['Products'][] = array(
			'ProductId'=>'1',
			'ProductItemsNum'=>'1',
			'ProductName'=>'Доставка',
			'ProductPrice'=>$tt,
		);
		$sum += $tt;
		$qty ++;
	}
	
	$request["MerchantGuid"] = $this->merchantGuid;
	$request['SelectedPaySystemId'] = $_COOKIE['cc_types'] ? $_COOKIE['cc_types'] : $this->GetMerchnatInfo(false, true);
	$request['Currency'] = $currency;
	$request['Language'] = $lang;
	
	$user_id = ($order->user_id < 1)?$order->user_id:1;

    $request['PaymentDetails'] = array(
       "MerchantInternalPaymentId"=>"$order_id",
       "MerchantInternalUserId"=>$user_id,
       "EMail"=>$order->billing_email,
       "PhoneNumber"=>$order->billing_phone,
       "CustomMerchantInfo"=>"",
       "StatusUrl"=>"$result_url",
       "ReturnUrl"=>"$success_url",
       "BuyerCountry"=>$order->billing_country,
       "BuyerFirstname"=>$order->billing_first_name,
       "BuyerPatronymic"=>$order->billing_company,
       "BuyerLastname"=>$order->billing_last_name,
       "BuyerStreet"=>$order->billing_address_1,
       "BuyerZone"=>"",
       "BuyerZip"=>$order->billing_postcode,
       "BuyerCity"=>$order->billing_city,

       "DeliveryFirstname"=>$order->shipping_first_name,
       "DeliveryLastname"=>$order->shipping_last_name,
       "DeliveryZip"=>$order->shipping_postcode, 
       "DeliveryCountry"=>$order->shipping_country,
       "DeliveryPatronymic"=>$order->shipping_company,
       "DeliveryStreet"=>$order->shipping_address_1,
       "DeliveryCity"=>$order->shipping_city,
       "DeliveryZone"=>"",
    );

		$request["Signature"] = md5(strtoupper($this->merchantGuid) .
			number_format($sum, 2, ".", "") . 
			$request["SelectedPaySystemId"] . 
			$request["PaymentDetails"]["EMail"] . 
			$request["PaymentDetails"]["PhoneNumber"] . 
			$request["PaymentDetails"]["MerchantInternalUserId"] . 
			$request["PaymentDetails"]["MerchantInternalPaymentId"] . 
			strtoupper($request["Language"]) . 
			strtoupper($request["Currency"]) . 
			strtoupper($this->merchnatSecretKey));

			$response = $this->sendRequestKaznachey(json_encode($request), "CreatePaymentEx");
			$result = json_decode($response, true);

		if($result['ErrorCode'] != 0){
			wp_redirect( home_url() ); exit;
		}
	
		echo(base64_decode($result["ExternalForm"]));
		exit();
	
    }

        function kaznachey_result(){
			global $woocommerce, $wpdb, $wpsc_cart, $wpsc_coupons;
			$woocommerce->logger()->add('kaznachey', 'returned');
			switch ($_GET['status'])
			{
				case 'done':
			$this->kaznachey_init();
			$request_json = file_get_contents('php://input');
			$request = json_decode($request_json, true);

			$request_sign = md5($request["ErrorCode"].
				$request["OrderId"].
				$request["MerchantInternalPaymentId"]. 
				$request["MerchantInternalUserId"]. 
				number_format($request["OrderSum"],2,".",""). 
				number_format($request["Sum"],2,".",""). 
				strtoupper($request["Currency"]). 
				$request["CustomMerchantInfo"]. 
				strtoupper($this->merchnatSecretKey));
			
				if($request['SignatureEx'] == $request_sign) {
					$order = new WC_Order($request["OrderId"]);
					$order->payment_complete();
					$order->add_order_note("Заказ оплачен. Платеж через www.kaznachey.ua");
					$woocommerce->logger()->add('kaznachey', 'OK');
				}
					wp_redirect( home_url() ); exit;
					
				break;		
				
				case 'success':
					wp_redirect( home_url() ); exit;
				break;
			}
		}

		function GetMerchnatInfo($id = false, $first = false){
			$this->kaznachey_init();
			$requestMerchantInfo = Array(
				"MerchantGuid"=>$this->merchantGuid,
				"Signature" => md5(strtoupper($this->merchantGuid) . strtoupper($this->merchnatSecretKey))
			);
			
			$resMerchantInfo = json_decode($this->sendRequestKaznachey(json_encode($requestMerchantInfo), 'GetMerchatInformation'),true); 
			if($first){
				return $resMerchantInfo["PaySystems"][0]['Id'];
			}elseif($id)
			{
				foreach ($resMerchantInfo["PaySystems"] as $key=>$paysystem)
				{
					if($paysystem['Id'] == $id){
						return $paysystem;
					}
				}
			}else{
			
				return $resMerchantInfo;
			}
		}
		
		protected function sendRequestKaznachey($jsonData, $method)
		{
			$curl = curl_init();
			if (!$curl)
				return false;

			curl_setopt($curl, CURLOPT_URL, $this->paymentKaznacheyUrl . $method);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER,
				array("Expect: ", "Content-Type: application/json; charset=UTF-8", 'Content-Length: '
					. strlen($jsonData)));
			curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, True);
			$response = curl_exec($curl);
			curl_close($curl);

			return $response;
		}
    }
}

add_filter( 'woocommerce_payment_gateways', 'add_wc_kaznachey_Payment_Gateway' );
function add_wc_kaznachey_Payment_Gateway( $methods ){
    $methods[] = 'WC_kaznachey_Payment_Gateway';
    return $methods;
}