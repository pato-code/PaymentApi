<?php

namespace App\Model\Payment;

use App\Model\Account\BankAccount;
use App\Model\SendRequest;
use App\Model\Transaction;
use App\Model\User;
use Illuminate\Database\Eloquent\Model;

class E15 extends Model
{
    const Payment = "payment";
    //
    public function payment(){
        return $this->belongsTo('App\Model\Payment\Payment' , 'payment_id');
    }
    public static function requestBuild($transaction_id , $ipin , $type)
    {
        $request = array();
        $transaction = Transaction::where("id", $transaction_id)->first();
        $user = User::where("id",$transaction->user_id)->first();
        $payment = Payment::where("transaction_id" , $transaction_id)->first();
        $e15 = E15::where("payment_id", $payment->id)->first();

        $request += ["applicationId", "Sadad"];
        $request += ["tranDateTime", $transaction->transDateTime];
        $uuid = $transaction->uuid;
        $request += ["UUID", $uuid];
        $userName = "";
        $userPassword = "";
        $entityId = "";
        $entityType = "";
        $authenticationType = "00";

        $bank = BankAccount::where("user_id", $user->id)->first();
        $PAN = $bank->PAN;

        $mbr = $bank->mbr;
        $expDate = $bank->expDate;
        //}
        $request += ["userName" => $userName];
        $request += ["userPassword" => $userPassword];
        $request += ["entityId" => $entityId];
        $request += ["entityType" => $entityType];
        $request += ["PAN" => $PAN];

        $request += ["mbr" => $mbr];
        $request += ["expDate" => $expDate];
        $request += ["IPIN" => $ipin];
        $request += ["authenticationType" => $authenticationType];
        $request += ["fromAccountType" => "00"];


        $tranCurrency = "SDG";
        //$tranAmount = $service->totalFees;
        $request += ["tranCurrency" => $tranCurrency];
        $request += ["tranAmount" => $e15->amount];

        $paymentInfo = "SERVICEID=".$type."/INVOICENUMBER=".$e15->invoice_no."/PHONENUMBER=".$e15->phone;
        $request +=["paymentInfo" => $paymentInfo];


        return $request;
    }
    public static function sendRequest($transaction_id , $ipin,$type){
        $request = self::requestBuild($transaction_id , $ipin,$type);
        $response = SendRequest::sendRequest($request , self::Payment);
        return $response;
    }
}
