<?php

namespace App\Http\Controllers;

use App\Functions;
use App\Model\Payment\Payment;
use App\Model\Payment\TopUp\TopUpBiller;
use App\Model\Response\PaymentResponse;
use App\Model\PublicKey;
use App\Model\Response\Response;
use App\Model\Response\TopUpResponse;
use App\Model\TopUpType;
use App\Model\Transaction;
use App\Model\TransactionType;
use Illuminate\Http\Request;
use App\Model\Payment\TopUp\TopUp as TopUpModel;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Webpatser\Uuid\Uuid;

class TopUp extends Controller
{
    //


    public function topUp(Request $request)
    {
        if ($request->isJson()) {
            $token = JWTAuth::parseToken();
            $user = $token->authenticate();

            //$user = JWTAuth::toUser($token);
            /******   Create Transaction Object  *********/
            $transaction = new Transaction();
            $transaction->user()->associate($user);
            $phone = $request->json()->get("phone");
            $biller = $request->json()->get("biller");
            $amount = $request->json()->get("amount");
            $ipin = $request->json()->get("IPIN");
            $bank = Functions::getBankAccountByUser($user);
            if (!isset($ipin)) {
                $response = array();
                $response += ["error" => true];
                $response += ["message" => "Insert IPIN Code "];
                return response()->json($response, 200);
            }
            if ($ipin !== $bank->IPIN){
                $response = array();
                $response = ["error" => true];
                $response = ["message" => "Wrong IPIN Code"];
                return response()->json($response,200);
            }
            if (!isset($phone)) {
                $response = array();
                $response += ["error" => true];
                $response += ["message" => "Insert phone "];
                return response()->json($response, 200);
            }
            if (!isset($biller)) {
                $response = array();
                $response += ["error" => true];
                $response += ["message" => "Insert biller "];
                return response()->json($response, 200);

            }
            if (!isset($amount)) {
                $response = array();
                $response += ["error" => true];
                $response += ["message" => "Insert amount "];
                return response()->json($response, 200);
            }
            $account = array();
            $account += ["PAN" => $bank->PAN];
            $account += ["IPIN" => $bank->IPIN];
            $account += ["expDate" => $bank->expDate];
            $account += ["mbr" => $bank->mbr];

            $transction_type = TransactionType::where('name', "Top Up")->pluck('id')->first();
            $transaction->transactionType()->associate($transction_type);
            $convert = Functions::getDateTime();
            $uuid = Uuid::generate()->string;
            $transaction->uuid = $uuid;
            $transaction->transDateTime = $convert;
            $transaction->status = "created";
            $transaction->user()->associate($user);
            $transaction->save();
            $payment = new Payment();
            $payment->transaction()->associate($transaction);
            $payment->amount = $amount;
            $payment->save();

            $transaction->status = "Create Account";
            $transaction->save();
            //$response = $this->checkAccount("bank", $account);
            //if ($response == null) {


            $biller_id = self::getBillerId($biller);

            $topUp = new TopUpModel();
            $topUp->payment()->associate($payment);
            $topUp->biller()->associate($biller_id);
            $topUp->phone=$phone;
            $topUp->payee_id=self::getPayeeId($biller);
            $topUp->save();

            $type_id = self::getTopUpTypeId("TopUp");
            //return response()->json(["type"=>$type_id],200);
            $topUp = self::getTopUp($type_id, $biller_id);
            //return response()->json(["type"=>$topUp],200);

            $transaction->status = "Save Top Up";
            $transaction->save();


            $ipin = PublicKey::sendRequest($ipin);
            if ($ipin == false){
                $res = array();
                $res +=["error" => true];
                $res +=["message" => "Server Error"];
                return response()->json($res , 200);
            }

            $response = TopUpModel::sendRequest($transaction->id , $ipin);
            $basicResonse = Response::saveBasicResponse($transaction, $response);
            $paymentResponse = PaymentResponse::savePaymentResponse($basicResonse, $payment, $response);
            self::saveTopUp($paymentResponse , $topUp , $response);
            $transaction->status = "done";
            $transaction->save();
            return response()->json($response, '200');


        } else {
            $response = array();
            $response += ["error" => true];
            $response += ["message" => "Request Must Be Json"];
            return response()->json($response, 200);
        }
    }



    public static function getBillerId($biller)
    {
        return TopUpBiller::where('name', $biller)->first();
    }

    public static function getTopUpTypeId($type)
    {
        return TopUpType::where('name', $type)->pluck('id')->first();
    }

    public static function getTopUp($type_id, $biller_id)
    {
        return TopUp::where('type_id', $type_id)->where('biller_id', $biller_id)->first();
    }
    public static function getPayeeId($biller){
        if ($biller == 'Zain')
            return "0010010001";
        elseif ($biller == 'Mtn')
            return "0010010003";
        else
            return "0010010005";
    }

    public static function saveTopUp($paymentResponse, $topUp ,  $response)
    {
        $top_up_response = new TopUpResponse();
        $top_up_response->PaymentResponse()->associate($paymentResponse);
        $top_up_response->TopUp()->associate($topUp);
        $top_up_response->status = "done";
        $top_up_response->save();
        return $top_up_response;
    }
}
