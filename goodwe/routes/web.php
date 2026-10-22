<?php

use App\Http\Controllers\AtendimentoHumanoController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ChargingController;
use App\Http\Controllers\ChargingSessionController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DemandControlController;
use App\Http\Controllers\DemandPricingController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegacyPaymentWebhookController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManagerAuthController;
use App\Http\Controllers\ManagerChargerController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SupportDashboardController;
use App\Http\Controllers\TotemChargingController;
use App\Http\Controllers\UserChatbotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WebChargingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Página inicial
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class);

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
*/

Route::get('/login', [UserController::class, 'login'])
    ->middleware('guest')->name('user.login');

Route::post('/login', [UserController::class, 'logar'])
    ->middleware(['guest', 'throttle:5,1'])->name('login-user');

Route::get('/recover-password', [UserController::class, 'showPasswordRecovery'])
    ->middleware('guest')->name('user.password.recovery');

Route::post('/recover-password/verify', [UserController::class, 'verifyPasswordRecovery'])
    ->middleware(['guest', 'throttle:5,1'])->name('user.password.verify');

Route::post('/recover-password/update', [UserController::class, 'updatePassword'])
    ->middleware(['guest', 'throttle:5,1'])->name('user.password.update');

Route::get('/create-user', [UserController::class, 'create'])
    ->middleware('guest')->name('user.cadastro');

Route::post('/store-user', [UserController::class, 'store'])
    ->middleware(['guest', 'throttle:10,1'])->name('user-store');

Route::post('/logout', [UserController::class, 'logout'])
    ->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Idiomas
|--------------------------------------------------------------------------
*/

Route::get('/locale/{locale}', LocaleController::class)->name('locale.set');

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

            Route::get('/dashboard', SupportDashboardController::class)->name('dashboard');

        });

    /*
    |--------------------------------------------------------------------------
    | Carregadores
    |--------------------------------------------------------------------------
    */

    Route::middleware('portal.user')->prefix('carregadores')
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

    Route::middleware('portal.user')->prefix('carteira')
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

    Route::middleware('portal.user')->group(function () {
        Route::post('/meus-pontos/resgatar', [PointsController::class, 'redeem'])->name('points.redeem');
        Route::get('/meus-pontos', [PointsController::class, 'index'])->name('points.index');
        Route::patch('/meu-perfil', [UserController::class, 'updateProfile'])->name('user.profile.update');
        Route::post('/meus-veiculos', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::middleware('portal.user')->delete('/meus-veiculos/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::get('/chatbot/historico', [UserChatbotController::class, 'history'])->name('user.chatbot.history');

        Route::get('/meu-perfil', [UserController::class, 'profile'])
            ->name('user.profile');

        Route::get('/inicio', [UserController::class, 'home'])
            ->name('user.home');

        /*
        |--------------------------------------------------------------------------
        | Reservas e recargas
        |--------------------------------------------------------------------------
        */

        Route::middleware('portal.user')->post('/carregadores/{charger:code}/reservar', [ReservationController::class, 'store'])
            ->middleware('throttle:20,1')->name('reservations.store');
        Route::middleware('portal.user')->post('/reservas/{reservation}/cancelar', [ReservationController::class, 'cancel'])
            ->name('reservations.cancel');
        Route::middleware('portal.user')->get('/reservas/{reservation}/estado', [ReservationController::class, 'status'])
            ->name('reservations.status');

        Route::middleware('portal.user')->get('/recargas/{session}', [ChargingSessionController::class, 'show'])
            ->name('charging-sessions.show');
        Route::middleware('portal.user')->post('/recargas/{session}/pagar', [ChargingSessionController::class, 'pay'])
            ->name('charging-sessions.pay');

        Route::post('/reservas/{reservation}/iniciar-teste', [WebChargingController::class, 'start'])
            ->middleware('throttle:60,1')->name('reservations.start-test');
        Route::post('/recargas/{session}/atualizar-teste', [WebChargingController::class, 'advance'])
            ->middleware('throttle:120,1')->name('charging-sessions.advance-test');
        Route::post('/recargas/{session}/encerrar-teste', [WebChargingController::class, 'end'])
            ->middleware('throttle:60,1')->name('charging-sessions.end-test');

        /*
        |--------------------------------------------------------------------------
        | Senha
        |--------------------------------------------------------------------------
        */

        Route::post('/atualizar-senha', [UserController::class, 'updatePassword'])
            ->name('user.update-password');
    });

    Route::prefix('carregar')->name('totens.')->group(function () {
        Route::get('/', [TotemChargingController::class, 'index'])->name('index');
        Route::get('/{totem_id}', [TotemChargingController::class, 'show'])->name('carregar');
        Route::post('/{totem_id}/iniciar', [TotemChargingController::class, 'iniciar'])->name('iniciar');
        Route::post('/{totem_id}/parar', [TotemChargingController::class, 'parar'])->name('parar');
        Route::post('/{totem_id}/pin', [TotemChargingController::class, 'enviarPin'])->name('pin');
        Route::get('/{totem_id}/status', [TotemChargingController::class, 'status'])->name('status');
    });

    /*

    /*
    |--------------------------------------------------------------------------
    | Chatbot do usuário
    |--------------------------------------------------------------------------
    */

    Route::middleware('portal.user')->get('/assistente', [UserChatbotController::class, 'page'])
        ->name('user.chatbot.page');

    Route::middleware('portal.user')->post('/chatbot', [UserChatbotController::class, 'chat'])
        ->name('user.chatbot');

    Route::middleware('portal.user')->delete('/chatbot', [UserChatbotController::class, 'clear'])
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

            Route::get('/perfil', [ManagerController::class, 'profile'])
                ->name('profile');
            Route::patch('/perfil', [UserController::class, 'updateProfile'])
                ->name('profile.update');

            Route::get('/estabelecimento/cadastro', [EstablishmentController::class, 'create'])
                ->name('establishment.setup');
            Route::post('/estabelecimento/cadastro', [EstablishmentController::class, 'store'])
                ->name('establishment.store');

            Route::middleware('establishment')->group(function () {
                Route::get('/estabelecimento', [EstablishmentController::class, 'edit'])
                    ->name('establishment.edit');
                Route::patch('/estabelecimento', [EstablishmentController::class, 'update'])
                    ->name('establishment.update');

                Route::get('/demanda/consumo', [DemandControlController::class, 'index'])
                    ->name('demand-control.index');
                Route::post('/demanda/consumo/sincronizar', [DemandControlController::class, 'syncFromFirestore'])
                    ->name('demand-control.sync');

                Route::get('/carregadores', [ManagerChargerController::class, 'index'])
                    ->name('chargers.index');

                Route::get('/carregadores/novo', [ManagerChargerController::class, 'create'])
                    ->name('chargers.create');

                Route::post('/carregadores', [ManagerChargerController::class, 'store'])
                    ->name('chargers.store');

                Route::get('/carregadores/{charger}/editar', [ManagerChargerController::class, 'edit'])
                    ->name('chargers.edit');

                Route::patch('/carregadores/{charger}', [ManagerChargerController::class, 'update'])
                    ->name('chargers.update');

                Route::get('/demanda', [DemandPricingController::class, 'index'])
                    ->name('demand.index');

               Route::post('/notificacoes/inscrever', [
             PushSubscriptionController::class,
                    'store',
                    ])
                     ->middleware('throttle:10,1')
                       ->name('notifications.subscribe');

                Route::get('/notificacoes/chave-publica', function () {
    return response()->json([
        'publicKey' => config('webpush.vapid.public_key'),
    ]);
})->name('notifications.public-key');

                   Route::delete('/notificacoes/inscrever', [
                PushSubscriptionController::class,
                   'destroy',
])
                   ->middleware('throttle:10,1')
                   ->name('notifications.unsubscribe');


                Route::post('/demanda/{peak}/decisao', [DemandPricingController::class, 'decide'])
                    ->name('demand.decide');

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
                AtendimentoHumanoController::class, 'index',
            ])->name('index');

            Route::post('/', [
                AtendimentoHumanoController::class, 'store',
            ])
                ->middleware('throttle:10,1')
                ->name('store');

            Route::get('/{atendimento}', [
                AtendimentoHumanoController::class, 'show',
            ])->name('show');

            Route::post('/{atendimento}/responder', [
                AtendimentoHumanoController::class, 'responder',
            ])->name('responder');

            Route::patch('/{atendimento}/concluir', [
                AtendimentoHumanoController::class, 'concluir',
            ])->name('concluir');
        });

    /*
    |--------------------------------------------------------------------------
    | Pagamentos
    |--------------------------------------------------------------------------
    */

    Route::middleware('portal.user')->post('/checkout', [CheckoutController::class, 'checkout'])
        ->name('checkout');

    Route::middleware('portal.user')->get('/pagamento/sucesso', [CheckoutController::class, 'success'])
        ->name('pagamento.sucesso');

    Route::middleware('portal.user')->get('/pagamento/erro', [CheckoutController::class, 'cancel'])
        ->name('pagamento.erro');

    Route::middleware('portal.user')->get('/pagamento/pendente', [CheckoutController::class, 'pending'])
        ->name('pagamento.pendente');
});
/* |--------------------------------------------------------------------------| Autenticação do gestor|-------------------------------------------------------------------------- */ Route::prefix('gestor')->name('manager.')->middleware('guest')->group(function () {
    Route::get('/login', [ManagerAuthController::class, 'login'])->name('login');
    Route::post('/login', [ManagerAuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/cadastro', [ManagerAuthController::class, 'create'])->name('register');
    Route::post('/cadastro', [ManagerAuthController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
});
/*
|--------------------------------------------------------------------------
| Webhook de pagamentos legados
|--------------------------------------------------------------------------
*/

Route::post('/webhook/mercadopago', LegacyPaymentWebhookController::class)
    ->name('mercadopago.webhook');

/*
|--------------------------------------------------------------------------
| Checkout Stripe
|--------------------------------------------------------------------------
*/

Route::prefix('stripe')->name('stripe.')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'stripeCheckout'])->name('create');
    Route::get('/sucesso', [CheckoutController::class, 'success'])->name('success');
    Route::get('/erro', [CheckoutController::class, 'cancel'])->name('cancel');
    Route::post('/webhook', [CheckoutController::class, 'webhook'])->name('webhook');
});
