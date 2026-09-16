<?php

namespace App\Http\Controllers;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use Illuminate\Http\Request;

class MercadoPagoController extends Controller
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));
    }

    public function checkout(Request $request)
    {
        $client = new PreferenceClient();

        try {
            $preference = $client->create([
                "items" => [
                    [
                        "id" => "prod-123",
                        "title" => "Camiseta preta",
                        "quantity" => 1,
                        "unit_price" => 79.90,
                        "currency_id" => "BRL",
                    ],
                ],
                "payer" => [
                    "email" => $request->user()?->email ?? "comprador@email.com",
                ],
                "external_reference" => "pedido-1001", // troque pelo ID real do seu pedido
                "back_urls" => [
                    "success" => route('pagamento.sucesso'),
                    "failure" => route('pagamento.erro'),
                    "pending" => route('pagamento.pendente'),
                ],
                "auto_return" => "approved",
                "notification_url" => route('mercadopago.webhook'),
            ]);
        } catch (MPApiException $e) {
            return back()->withErrors(['mp' => $e->getMessage()]);
        }

        return redirect()->to($preference->init_point);
    }

    public function sucesso(Request $request)
    {
        return view('pagamento.sucesso');
    }

    public function erro(Request $request)
    {
        return view('pagamento.erro');
    }

    public function pendente(Request $request)
    {
        return view('pagamento.pendente');
    }
}