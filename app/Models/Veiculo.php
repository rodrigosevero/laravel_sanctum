<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Veiculo extends Model
{

    use SoftDeletes;

    /**
     * Opcional, informar a coluna deleted_at como um Mutator de data
     *
     * @var array
     */
    protected $dates = ['deleted_at'];
    protected $table = 'veiculo';
    use HasFactory;

    protected $fillable = [
        'id',
        'cod_proprietario',
        'nome_razao',
        'autozam',
        'cod_marca',
        'cod_modelo',
        'cod_versao',
        'renavam',
        'chassi',
        'placa',
        'km',
        'motor',
        'anomodelo',
        'anofabricacao',
        'cor',
        'portas',
        'combustivel',
        'valor',
        'custo',
        'valor_consignado',
        'valor_compra',
        'observacao',
        'estoque',
        'estado',
        'unico_dono',
        'tipo_veiculo',
        'tipo_entrada',
        'exibeab',
        'ativo',
        'data_cadastro',
        'tc',
        'ac',
        'sc',
        'vsl',
        'loj_ou_part',
        'fotoc',
        'cep_loc',
        'estado_loc',
        'cidade_loc',
        'bairro',
        'rua',
        'comprado',
        'prioridade',
        'cod_contrato',
        'clausulas',
        'condicoes_consignado',
        'venda',
        'cliente_id_venda',
        'contrato_id_venda',
        'cod_cliente'
    ];




    public function scopeFiltroLojista($query)
    {
        if (Auth::user()->can('lojista'))
            return $query->where('cod_cliente', Auth::user()->cliente_id);

        return $query;
    }

    public function scopeFiltroPorLoja($query, $request)
    {
        if (Auth::user()->can('admin') && $request->lojista_id)
            return $query->where('cod_cliente', '=', $request->lojista_id);                      

        return $query;
    }


    public function lojista()
    {
        return $this->belongsTo(Lojista::class, 'cod_cliente');
    }

    public function opcionais()
    {
        return $this->hasMany(ViewOpcionais::class, 'cod_veiculo');
    }

    public function scopeFilter($query, $request)
    {

        return $query->when($request->has('ativo') && $request->ativo !== null, function ($query) use ($request) {
            $query->where('ativo', '=', $request->ativo);
        })
            ->when($request->has('tipo_veiculo') && $request->tipo_veiculo !== null, function ($query) use ($request) {
                $query->where('tipo_veiculo', $request->tipo_veiculo);
            });
            
            // ->when($request->has('lojista_id') && $request->lojista_id !== null, function ($query) use ($request) {
            //     $query->where('cod_cliente', $request->lojista_id);
            // })
            // ->when($request->has('search') && $request->search !== null, function ($query) use ($request) {
            //     $query->where('autozam', 'LIKE', '%' . $request->search . '%')
            //         ->orWhere('placa', 'LIKE', '%' . $request->search . '%')
            //         ->orWhere('renavam', 'LIKE', '%' . $request->search . '%');
            // });
            // ->when($request->has('ordenacao') && $request->ordenacao !== null, function ($query) use ($request) {
            //     $query->orderby('id', 'ASC');
            // });
    }

    
}
