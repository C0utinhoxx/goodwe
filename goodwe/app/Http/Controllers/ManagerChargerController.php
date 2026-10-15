<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterManagerChargersRequest;
use App\Http\Requests\ManagerChargerRequest;
use App\Models\Charger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ManagerChargerController extends Controller
{
    public function index(FilterManagerChargersRequest $request): View
    {
        $search = $request->validated('search');
        $chargers = $request->user()->establishment->chargers()->where('is_demo', false)->with('establishment');
        if (filled($search)) {
            $chargers->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', '%'.$search.'%')
                    ->orWhereLike('code', '%'.$search.'%')
                    ->orWhereLike('location', '%'.$search.'%')
                    ->orWhereLike('connector', '%'.$search.'%');
            });
        }

        return view('manager.chargers', [
            'establishment' => $request->user()->establishment,
            'chargers' => $chargers->orderBy('name')->orderBy('id')->paginate(12)->withQueryString(),
            'search' => $search ?? '',
        ]);
    }

    public function create(): View
    {
        return view('manager.charger-form', ['charger' => null]);
    }

    public function store(ManagerChargerRequest $request): RedirectResponse
    {
        try {
            $request->user()->establishment->chargers()->create([...$request->safe()->only(['code', 'name', 'location', 'power_kw', 'connector', 'lat', 'lng']), 'activation_status' => 'pending', 'physical_status' => 'unavailable']);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Este identificador já está cadastrado. Não é possível assumir um equipamento de outro cadastro.']);
        }

        return redirect()->route('manager.chargers.index')->with('success', 'Carregador cadastrado. A ativação física aguarda comprovação pela integração do equipamento.');
    }

    public function edit(Request $request, Charger $charger): View
    {
        abort_unless($charger->establishment_id === $request->user()->establishment->id, 403);

        return view('manager.charger-form', compact('charger'));
    }

    public function update(ManagerChargerRequest $request, Charger $charger): RedirectResponse
    {
        $charger->update($request->safe()->only(['name', 'location', 'power_kw', 'connector', 'lat', 'lng']));

        return redirect()->route('manager.chargers.index')->with('success', 'Informações administrativas do carregador atualizadas.');
    }
}
