<?php
class EpayPaymentApi {

    private $epay_apikey;
    private $epay_pos;
    private $base_url;

    public function __construct($apikey, $posid)
    {
        $this->epay_apikey = $apikey;
        $this->epay_pos = $posid;
        $this->base_url = "https://payments.epay.eu";
    }

    public function createPaymentRequest($json_data) // CIT authorization
    {
        $params = json_decode($json_data);

        $ePayParameters = array(
            "reference" => $params->orderid,
            "pointOfSaleId" => $this->epay_pos,
            "amount" => $params->amount,
            "currency" => $params->currency,
            "scaMode" => "NORMAL",
            "timeout" => 120,
            "instantCapture" => "OFF",
            "maxAttempts" => 10,
            "notificationUrl" => $params->callbackurl,
            "successUrl" => $params->accepturl,
            "failureUrl" => $params->cancelurl,
            "attributes" => array("wcorderid" => $params->wcorderid)
        );

        if(isset($params->minimumuserage) && $params->minimumuserage > 0)
        {
            $ePayParameters['ageVerification']['minimumAge'] = intval($params->minimumuserage);
            $ePayParameters['ageVerification']['country'] = $params->ageverificationcountry;
        }
        
        if(isset($params->subscription) && $params->subscription == 1)
        {
            $ePayParameters['subscription']['type'] = "UNSCHEDULED";
            // $ePayParameters['subscription']['interval']['period'] = "DAY";
            // $ePayParameters['subscription']['interval']['frequency'] = 3;
        }

        if(isset($params->subscription) && $params->subscription == 2)
        {
            $ePayParameters['subscription']['id'] = $params->subscriptionid;
            $ePayParameters['subscription']['type'] = "UNSCHEDULED";
            // $ePayParameters['subscription']['interval']['period'] = "DAY";
            // $ePayParameters['subscription']['interval']['frequency'] = 3;
        }
        
        $endpoint_URL = $this->base_url."/public/api/v1/cit";
        $result = $this->post($endpoint_URL, $ePayParameters);

        return $result;
    }

    public function authorize($subscriptionId, $amount, $currency, $reference, $instantCapture, $textOnStatement, $notificationUrl)  // MIT authorization
    {
        $ePayParameters = array(
            "subscriptionId" => $subscriptionId,
            "amount" => $amount,
            "currency" => $currency,
            "reference" => $reference,
            "instantCapture" => $instantCapture,
            "textOnStatement" => $reference,
            "notificationUrl" => $notificationUrl);
        
        $endpoint_URL = $this->base_url."/public/api/v1/mit";
        $result = $this->post($endpoint_URL, $ePayParameters);

        return $result;
    }



    public function capture($transactionId, $amount)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/capture";
        
        $result = $this->post($endpoint_URL, $ePayParameters);

        return $result;
    }

    public function refund($transactionId, $amount)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/refund";

        $result = $this->post($endpoint_URL, $ePayParameters);

        return $result;
    }

    public function void($transactionId, $amount=-1)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/void";
        
        $result = $this->post($endpoint_URL, $ePayParameters);

        return $result;
    }

    public function delete_subscription($subscription_id): bool
    {
        $endpoint_URL = $this->base_url."/public/api/v1/subscriptions/".$subscription_id;
        
        $result = $this->delete($endpoint_URL);

        return $result;
    }

    public function payment_info($transactionId)
    {
        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId;

        $authamount = 0;
        $capturedamount = 0;
        $refundedamount = 0;
        $voidedamount = 0;

        $json_result = $this->get($endpoint_URL);

        $data = json_decode($json_result);
        if(!isset($data->success) && is_array($data->operations))
        {
            $data->history = new stdClass();

            foreach($data->operations AS $operation)
            {
                if($operation->state == "SUCCESS" && ($operation->type == "AUTHORIZATION" || $operation->type == "SALE"))
                {
                    $authamount += $operation->amount;
                }
                
                if($operation->state == "SUCCESS" && ($operation->type == "CAPTURE" || $operation->type == "SALE"))
                {
                    $capturedamount += $operation->amount;
                }
                elseif($operation->state == "SUCCESS" && $operation->type == "REFUND")
                {
                    $refundedamount += $operation->amount;
                }
                elseif($operation->state == "SUCCESS" && $operation->type == "VOID")
                {
                    $voidedamount += $operation->amount;
                }

                $minorunits    = EpayPaymentHelper::get_currency_minorunits( $data->transaction->currency );
                $amount_formatted = EpayPaymentHelper::convert_price_from_minorunits( $operation->amount, $minorunits );
                
                $data->history->TransactionHistoryInfo[] = (object) ["created"=>$operation->createdAt, "username"=>"User", "eventMsg"=> $operation->type." - ".$operation->state." - ".$amount_formatted." ".$data->transaction->currency];
            }

            $data->authamount = $authamount;
            $data->capturedamount = $capturedamount;
            $data->creditedamount = $refundedamount;
            $data->voidedamount = $voidedamount;

            if($capturedamount == 0)
            {
                $data->status = "PAYMENT_NEW";
            }
            else
            {
                $data->status = "PAYMENT_CAPTURED";
            }

            $data->currency = EpayPaymentHelper::get_iso_code($data->transaction->currency, true);
            $data->cardtypeid = $this->cardtypename_to_cardtypeid($data->transaction->paymentMethodSubType);
            $data->transactionid = $data->transaction->id;
            
            $data->paymentMethodType = $data->transaction->paymentMethodType;
            $data->paymentMethodSubType = $data->transaction->paymentMethodSubType;
        }
        
        return json_encode($data);
    }

    private function post($endpoint_URL, array $data)
    {
        $response = wp_remote_post($endpoint_URL, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->epay_apikey,
            ),
            'body'    => wp_json_encode($data),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($http_code === 200) {
            return $body;
        } else {
            return $body;
        }
    }
    
    private function get($endpoint_URL)
    {
        $response = wp_remote_get($endpoint_URL, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->epay_apikey,
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($http_code === 200) {
            return $body;
        } else {
            // return false;
            return $body;
        }
    }

    private function delete( $endpoint_URL, array $data = [] ):bool
    {
        $args = [
            'method'  => 'DELETE',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->epay_apikey,
            ],
            'timeout' => 15,
        ];

        if ( ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        $response = wp_remote_request( $endpoint_URL, $args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );

        if ( in_array( $http_code, [ 200, 202, 204 ], true ) ) {
            return true;
        }

        return false;
    }

    private function cardtypename_to_cardtypeid($cardtypename)
    {
        $card_type_array = ['Dankort' => 1, 'Visa' => 3, 'Mastercard' => 4];

        if(isset($card_type_array[$cardtypename]))
        {
            return $card_type_array[$cardtypename];        
        }
        else
        {
            return false;
        }
    }
}
?>
