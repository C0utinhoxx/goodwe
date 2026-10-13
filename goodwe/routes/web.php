<?php

use App\Http\Controllers\AtendimentoHumanoController;
use App\Http\Controllers\ChargingController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\UserChatbotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Página inicial
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('user.login');
    }

    $user = auth()->user();

    if ($user->isSuporte()) {
        return redirect()->route('support.dashboard');
    }

    if ($user->isGestor()) {
        return redirect()->route('manager.dashboard');
    }

    return redirect()->route('user.home');
});

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
*/

Route::get('/login-user', [UserController::class, 'login'])
    ->name('user.login');

Route::post('/login', [UserController::class, 'logar'])
    ->name('login-user');

Route::get('/recover-password', [UserController::class, 'showPasswordRecovery'])
    ->name('user.password.recovery');

Route::post('/recover-password/verify', [UserController::class, 'verifyPasswordRecovery'])
    ->name('user.password.verify');

Route::post('/recover-password/update', [UserController::class, 'updatePassword'])
    ->name('user.password.update');

Route::get('/create-user', [UserController::class, 'create'])
    ->name('user.cadastro');

Route::post('/store-user', [UserController::class, 'store'])
    ->name('user-store');

Route::post('/logout', [UserController::class, 'logout'])
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Idiomas
|--------------------------------------------------------------------------
*/

Route::get('/locale/{locale}', function (string $locale) {
    if (! in_array($locale, ['pt', 'en', 'es'])) {
        abort(400);
    }

    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale.set');

/*
|--------------------------------------------------------------------------
| Rotas autenticadas
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Central de Atendimento - Suporte
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:access-support')
        ->prefix('suporte')
        ->name('support.')
        ->group(function () {

            Route::get('/dashboard', function () {
                return view('suporte.dashboard');
            })->name('dashboard');

        });

    /*
    |--------------------------------------------------------------------------
    | Carregadores
    |--------------------------------------------------------------------------
    */

    Route::prefix('carregadores')
        ->name('chargers.')
        ->group(function () {

            Route::get('/', [ChargingController::class, 'index'])
                ->name('index');

            Route::post('/{charger}/agendar', [ChargingController::class, 'schedule'])
                ->name('schedule');

            Route::post('/{charger}/liberar', [ChargingController::class, 'release'])
                ->name('release');
        });

    /*
    |--------------------------------------------------------------------------
    | Carteira
    |--------------------------------------------------------------------------
    */

    Route::prefix('carteira')
        ->name('wallet.')
        ->group(function () {

            Route::get('/', [WalletController::class, 'index'])
                ->name('index');

            Route::get('/adicionar', [WalletController::class, 'depositForm'])
                ->name('deposit.form');

            Route::post('/adicionar', [WalletController::class, 'storeDeposit'])
                ->name('deposit.store');

            Route::get('/historico', [WalletController::class, 'history'])
                ->name('history');
        });

    /*
    |--------------------------------------------------------------------------
    | Área do usuário
    |--------------------------------------------------------------------------
    */

    Route::get('/meu-perfil', [UserController::class, 'profile'])
        ->name('user.profile');

    Route::get('/inicio', [UserController::class, 'home'])
        ->name('user.home');

    /*
    |--------------------------------------------------------------------------
    | Chatbot do usuário
    |--------------------------------------------------------------------------
    */

    Route::get('/assistente', [UserChatbotController::class, 'page'])
        ->name('user.chatbot.page');

    Route::post('/chatbot', [UserChatbotController::class, 'chat'])
        ->name('user.chatbot');

    Route::delete('/chatbot', [UserChatbotController::class, 'clear'])
        ->name('user.chatbot.clear');

    /*
    |--------------------------------------------------------------------------
    | Área do gestor
    |--------------------------------------------------------------------------
    */

    Route::middleware('gestor')
        ->prefix('gestor')
        ->name('manager.')
        ->group(function () {

            Route::get('/dashboard', [ManagerController::class, 'dashboard'])
                ->name('dashboard');

            Route::get('/dashboard/export', [ManagerController::class, 'exportSessions'])
                ->name('dashboard.export');

            Route::get('/assistente', [ChatbotController::class, 'page'])
                ->name('chatbot.page');

            Route::post('/chatbot', [ChatbotController::class, 'chat'])
                ->name('chatbot');

            Route::delete('/chatbot', [ChatbotController::class, 'clear'])
                ->name('chatbot.clear');
        });

    /*
    |--------------------------------------------------------------------------
    | Tickets - Atendimento Humano
    |--------------------------------------------------------------------------
    */

    Route::prefix('tickets')
        ->name('tickets.')
        ->group(function () {

            Route::get('/', [
                AtendimentoHumanoController::class, 'index'
            ])->name('index');

            Route::post('/', [
                AtendimentoHumanoController::class, 'store'
            ])
                ->middleware('throttle:10,1')
                ->name('store');

            Route::get('/{atendimento}', [
                AtendimentoHumanoController::class, 'show'
            ])->name('show');

            Route::post('/{atendimento}/responder', [
                AtendimentoHumanoController::class, 'responder'
            ])->name('responder');

            Route::patch('/{atendimento}/concluir', [
                AtendimentoHumanoController::class, 'concluir'
            ])->name('concluir');
        });

    /*
    |--------------------------------------------------------------------------
    | Pagamentos
    |--------------------------------------------------------------------------
    */

    Route::post('/checkout', [MercadoPagoController::class, 'checkout'])
        ->name('checkout');

    Route::get('/pagamento/sucesso', [MercadoPagoController::class, 'sucesso'])
        ->name('pagamento.sucesso');

    Route::get('/pagamento/erro', [MercadoPagoController::class, 'erro'])
        ->name('pagamento.erro');

    Route::get('/pagamento/pendente', [MercadoPagoController::class, 'pendente'])
        ->name('pagamento.pendente');
});

/*
|--------------------------------------------------------------------------
| Webhook Mercado Pago
|--------------------------------------------------------------------------
*/

Route::post('/webhook/mercadopago', [MercadoPagoController::class, 'webhook'])
    ->name('mercadopago.webhook');
