<?php

namespace App\Http\Controllers;

use App\Functions;
use App\Model\Payment\Payment;
use App\Model\PublicKey;
use App\Model\Response\PaymentResponse;
use App\Model\Response\Response;
use App\Model\Transaction;
use App\Model\TransactionType;
use App\Model\Response\ElectricityResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Webpatser\Uuid\Uuid;
use App\Model\Payment\Electricity as ElectricityModel;

class Electricity extends Controller
{
    //
    public function electricity(Request $request)
    {
        if ($request->isJson()) {
            $token = JWTAuth::parseToken();
            $user = $token->authenticate();
            //$user = JWTAuth::toUser($token);
            /******   Create Transaction Object  *********/
            $transaction = new Transaction();
            $transaction->user()->associate($user);
            $meter = $request->json()->get("meter");
            $amount = $request->json()->get("amount");
            $ipin = $request->json()->get("IPIN");
            $bank = Functions::getBankAccountByUser($user);
            $account = array();
            if ($ipin !== $bank->IPIN){
                $response = array();
                $response = ["error" => true];
                $response = ["message" => "Wrong IPIN Code"];
                return response()->json($response,200);
            }
            if (!isset($meter)) {
                $response = array();
                $response += ["error" => true];
                $response += ["message" => "Insert Meter Number "];
                return response()->json($response, 200);
            }
            if (!isset($amount)) {
                $response = array();
                $response += ["error" => true];
                $response += ["message" => "Insert amount "];
                return response()->json($response, 200);
            }
            $account += ["PAN" => $bank->PAN];
            $account += ["IPIN" => $bank->IPIN];
            $account += ["expDate" => $bank->expDate];
            $account += ["mbr" => $bank->mbr];

            $transction_type = TransactionType::where('name', "Electericity")->pluck('id')->first();
            $transaction->transactionType()->associate($transction_type);
            $convert = Functions::getDateTime();
            $uuid = Uuid::generate()->string;
            /*
             *  Create Transaction Object
             *
             */
            $transaction->uuid = $uuid;
            $transaction->transDateTime = $convert;
            $transaction->status = "created";
            $transaction->save();
            /*
             *   Create Payment Object
             */
            $payment = new Payment();
            $payment->transaction()->associate($transaction);
            $payment->amount = $amount;
            $payment->save();

            $transaction->status = "Create Electricity";
            $transaction->save();


            $transaction->status = "Save Buy Electricity";
            $transaction->save();

            $electricity = new ElectricityModel();
            $electricity->meter = $meter;
            $electricity->save();


            $ipin = PublicKey::sendRequest($ipin);
            if ($ipin == false){
                $res = array();
                $res += ["error" => true];
                $res += ["message" => "Server Error"];
                return response()->json($res,200);
            }

            $response = ElectricityModel::sendRequest($transaction->id  , $ipin);

            $basicResonse = Response::saveBasicResponse($transaction, $response);
            $paymentResponse = PaymentResponse::savePaymentResponse($basicResonse, $payment, $response);
            //$electriciyResponse =

            if ($response->responseCode != 0){
                $transaction->status = "Server Error";
                $transaction->save();
                $res = array();
                $res += ["error" => true];
                $res += ["EBS" , $response];

                return response()->json($res, '200');
            }
            else{
                $transaction->status = "done";
                $transaction->save();
                $res = array();
                $info = array();
                $info += ["meterFees" => $response->billInfo->meterFees];
                $info += ["netAmount" => $response->billInfo->netAmount];
                $info += ["unitsInKWh" => $response->billInfo->unitsInKWh];
                $info += ["waterFees" => $response->billInfo->waterFees];
                $info += ["token" => $response->billInfo->token];
                $info += ["customerName" => $response->billInfo->customerName];
                $info += ["opertorMessage" => $response->billInfo->opertorMessage];

                $res += ["error" => false];
                $res += ["message" => "Done Successfully"];
                $res += ["info" => $info];

                return response()->json($response, '200');
            }


        } else {
            $response = array();
            $response += ["error" => true];
            $response += ["message" => "Request Must Be Json"];
            return response()->json($response, 200);
        }
    }
    public static function saveElectriciyResponse($paymentResponse , $electricity , $response){
        $electriciy_response = new ElectricityResponse();
        $electriciy_response->PaymentResponse()->associate($paymentResponse);
        $electriciy_response->Electriciy()->associate($electricity);
        $bill_info = $response->billInfo;
        $electriciy_response->fill($bill_info);
        $electriciy_response->save();
        return $electriciy_response;
    }
}
