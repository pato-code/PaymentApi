<?php

namespace App\Http\Controllers;

use App\Functions;
use App\Model\PublicKey;
use App\Model\Transaction;
use App\Model\TransactionType;
use App\Model\Transfer;
use App\Model\TransferType;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Webpatser\Uuid\Uuid;
use App\Model\CardTransfer as CardTransferModel;

class CardTransfer extends Controller
{
    //

    public function card_transfer(Request $request){
        if ($request->isJson()){
            $token = JWTAuth::parseToken();
            $user = $token->authenticate();
            $to = $request->json()->get("to");
            $amount = $request->json()->get("amount");
            $ipin =  $request->json()->get("IPIN");
            $bank = Functions::getBankAccountByUser($user);
            $account = array();
            if ($ipin == $bank->IPIN){
                $response = array();
                $response = ["error" => true];
                $response = ["message" => "Wrong IPIN Code"];
                return response()->json($response,200);
            }
            $account += ["PAN" => $bank->PAN];
            $account += ["IPIN" => $bank->IPIN];
            $account += ["expDate" => $bank->expDate];
            $account += ["mbr" => $bank->mbr];

            if (!isset($amount)){
                $response = array();
                $response += ["error" => true];
                $response += ["message" => "Send Amount with the request"];
                return response()->json($response,200);
            }
            if (!isset($to)){
                $response = array();
                $response += ["error" => true];
                $response += ["message" => "Send The Card Number You want to send To"];
                return response()->json($response,200);
            }

            //$user = JWTAuth::toUser($token);
            /******   Create Transaction Object  *********/
            $transaction = new Transaction();
            $transaction->user()->associate($user);
            $transaction_type = TransactionType::where('name', "Card Transfer")->pluck('id')->first();
            $transaction->transactionType()->associate($transaction_type);
            $convert = Functions::getDateTime();


            $uuid = Uuid::generate()->string;
            //$uuid=Uuid::randomBytes(16);

            $transaction->uuid = $uuid;
            $transaction->transDateTime = $convert;
            $transaction->status = "created";
            $transaction->save();
            $transfer = new Transfer();
            $transfer->transaction()->associate($transaction);
            $transfer->amount = $amount;
            $transfer_type=TransferType::where("name","Card Transfer")->first();
            $transfer->type()->associate($transfer_type);
            $transfer->save();
            $card_transfer = new CardTransferModel();
            $card_transfer->transfer()->associate($transfer);
            $card_transfer->toCard = $to;
            $card_transfer->save();

            $ipin = PublicKey::sendRequest($ipin);
            if ($ipin == false){
                $res = array();
                $res += ["error" => true];
                $res += ["message" => "Server Error"];
                return response()->json($res,200);
            }

            $response = CardTransferModel::sendRequest($transaction->id,$ipin);
            if ($response->responseCode != 0){
                $res = array();
                $res += ["error" => true];
                $res += ["message" => "Some Error Found"];
                return response()->json($res,200);
            }
            else{
                $res = array();
                $res += ["error" => false];
                $res += ["message" => "Done Successfully"];
                $res += ["balance" => $response->balance];
                return response()->json($res,200);
            }

        }
        else{
            $response = array();
            $response += ["error" => true];
            $response += ["message" => "Request Must Send In Json"];
            return response()->json(["data"=>$response],200);
        }
    }
}
