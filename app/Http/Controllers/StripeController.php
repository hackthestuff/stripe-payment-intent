<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StripeController extends Controller
{
    const BASE_URL = 'https://api.stripe.com';
    const SECRET_KEY = 'sk_test_XXXXXXXXXXXXXXXXXXXXXXX';

    /**
     * show payment page
     *
     * @return void
     */
    public function form()
    {
        return view('form');
    }

    /**
     * submit payment page
     *
     * @return void
     */
    public function submit(Request $request)
    {
        $input = $request->validate([
            'card_no' => 'required',
            'exp_month' => 'required',
            'exp_year' => 'required',
            'cvc' => 'required',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'line1' => 'required',
            'postal_code' => 'required',
            'email' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'required',
            'amount' => 'required',
            'currency' => 'required',
        ]);

        $input['transaction_id'] = \Str::random(18); // random string for transaction id

        // save to database
        // it is recomended to save before sending data to stripe server
        // so we can verify after return from 3ds page
        // \DB::table('transactions')
        //     ->insert($input);

        // create payment method request
        // see documentation below for options
        // https://stripe.com/docs/api/payment_methods/create
        $payment_url = self::BASE_URL.'/v1/payment_methods';

        $payment_data = [
            'type' => 'card',
            'card[number]' => $input['card_no'],
            'card[exp_month]' => $input['exp_month'],
            'card[exp_year]' => $input['exp_year'],
            'card[cvc]' => $input['cvc'],
            'billing_details[address][city]' => $input['city'],
            'billing_details[address][state]' => $input['state'],
            'billing_details[address][country]' => $input['country'],
            'billing_details[address][line1]' => $input['line1'],
            'billing_details[address][postal_code]' => $input['postal_code'],
            'billing_details[email]' => $input['email'],
            'billing_details[name]' => $input['first_name'].' '.$input['last_name'],
            'billing_details[phone]' => $input['phone'],
        ];

        $payment_payload = http_build_query($payment_data);

        $payment_headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer '.self::SECRET_KEY
        ];

        // sending curl request
        // see last function for code
        $payment_body = $this->curlPost($payment_url, $payment_payload, $payment_headers);

        $payment_response = json_decode($payment_body, true);

        // create payment intent request if payment method response contains id
        // see below documentation for options
        // https://stripe.com/docs/api/payment_intents/create
        if (isset($payment_response['id']) && $payment_response['id'] != null) {

            $request_url = self::BASE_URL.'/v1/payment_intents';

            $request_data = [
                'amount' => $input['amount'] * 100, // multiply amount with 100
                'currency' => $input['currency'],
                'payment_method_types[]' => 'card',
                'payment_method' => $payment_response['id'],
                'confirm' => 'true',
                'capture_method' => 'automatic',
                'return_url' => route('stripeResponse', $input['transaction_id']),
                'payment_method_options[card][request_three_d_secure]' => 'automatic',
            ];

            $request_payload = http_build_query($request_data);

            $request_headers = [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Bearer '.self::SECRET_KEY
            ];

            // another curl request
            $response_body = $this->curlPost($request_url, $request_payload, $request_headers);

            $response_data = json_decode($response_body, true);

            // transaction required 3d secure redirect
            if (isset($response_data['next_action']['redirect_to_url']['url']) && $response_data['next_action']['redirect_to_url']['url'] != null) {

                return redirect()->away($response_data['next_action']['redirect_to_url']['url']);
            
            // transaction success without 3d secure redirect
            } elseif (isset($response_data['status']) && $response_data['status'] == 'succeeded') {

                return redirect()->route('stripeResponse', $input['transaction_id'])->with('success', 'Payment success.');

            // transaction declined because of error
            } elseif (isset($response_data['error']['message']) && $response_data['error']['message'] != null) {
                
                return redirect()->route('stripeResponse', $input['transaction_id'])->with('error', $response_data['error']['message']);

            } else {

                return redirect()->route('stripeResponse', $input['transaction_id'])->with('error', 'Something went wrong, please try again.');
            }

        // error in creating payment method
        } elseif (isset($payment_response['error']['message']) && $payment_response['error']['message'] != null) {

            return redirect()->route('stripeResponse', $input['transaction_id'])->with('error', $payment_response['error']['message']);

        }
    }

    /**
     * response from 3ds page
     *
     * @return Stripe response
     */
    public function response(Request $request, $transaction_id)
    {
        $request_data = $request->all();

        // if only stripe response contains payment_intent
        if (isset($request_data['payment_intent']) && $request_data['payment_intent'] != null) {

            // here we will check status of the transaction with payment_intents from stripe server
            $get_url = self::BASE_URL.'/v1/payment_intents/'.$request_data['payment_intent'];

            $get_headers = [
                'Authorization: Bearer '.self::SECRET_KEY
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $get_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $get_headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $get_response = curl_exec($ch);

            curl_close ($ch);

            $get_data = json_decode($get_response, 1);

            // get record of transaction from database
            // so we can verify with response and update the transaction status
            // $input = \DB::table('transactions')
            //     ->where('transaction_id', $transaction_id)
            //     ->first();

            // here you can check amount, currency etc with $get_data
            // which you can check with your database record
            // for example amount value check
            if ($input['amount'] * 100 == $get_data['amount']) {
                // nothing to do
            } else {
                // something wrong has done with amount
            }

            // succeeded means transaction success
            if (isset($get_data['status']) && $get_data['status'] == 'succeeded') {

                return view('response')->with('success', 'Payment success.');

                // update here transaction for record something like this
                // $input = \DB::table('transactions')
                //     ->where('transaction_id', $transaction_id)
                //     ->update(['status' => 'success']);

            } elseif (isset($get_data['error']['message']) && $get_data['error']['message'] != null) {
                
                return view('response')->with('error', $get_data['error']['message']);

            } else {

                return view('response')->with('error', 'Payment request failed.');
            }
        } else {

            return view('response')->with('error', 'Payment request failed.');

        }
    }

    /**
     * create curl request
     * we have created seperate method for curl request
     * instead of put code at every request
     *
     * @return Stripe response
     */
    private function curlPost($url, $data, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        curl_close ($ch);

        return $response;
    }
}
