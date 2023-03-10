<?php

namespace App\Http\Controllers;

use App\Models\Veiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

class VeiculoController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $veiculos = Veiculo::query()
            ->where('venda', 0)
            ->paginate(10);

        // dd($veiculos->lojista);


        return response()->json([
            'veiculos' => $veiculos,            
        ]);

    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function form(Request $request)
    {
        $usuario_id = Auth::user()->id;
        $cliente_id = Auth::user()->cliente_id;

        $comprador = Lojista::find($cliente_id);

        $proprietarios = Proprietario::query()
            ->where('nome_razao', 'LIKE', "%{$request->search}%")
            ->orWhere('cpf_cnpj', 'LIKE', "%{$request->search}%")
            ->orWhere('tel_celular', 'LIKE', "%{$request->search}%")
            ->orWhere('tel_comercial', 'LIKE', "%{$request->search}%")
            // ->orWhere('email', 'LIKE', "%{$searchTerm}%")
            ->get();


        if (old('modelo_id') != "") {
            $old_modelo = Modelo::where('id', old('modelo_id'))->firstorfail();
        } else {
            $old_modelo = '';
        }

        if (old('versao_id') != "") {
            $old_versao = Versao::where('id', old('versao_id'))->first();
        } else {
            $old_versao = '';
        }

        $request->tipo_veiculo == 0 ? $tipo_marca = 0 : $tipo_marca = 1;

        $marcas_favoritas = Marca::where('tipo_marca', $tipo_marca)->where('favorito', 1)->orderBy('favorito', 'desc')->orderBy('nome', 'asc')->get();
        $marcas = Marca::where('tipo_marca', $tipo_marca)->where('favorito', 0)->orderBy('nome', 'asc')->get();

        $modelos = Modelo::where('id', '0')->orderBy('nome_modelo', 'asc')->get();
        $versoes = Versao::where('id', '0')->orderBy('nome_versao', 'asc')->get();

        if ($request->cliente_id) {
            $proprietario = Proprietario::where('id', $request->cliente_id)->firstorfail();
        } else {
            $proprietario = NULL;
        }



        $opcionais = Opcional::where('tipo_veiculo', $request->tipo_veiculo)->orderBy('opcional', 'asc')->get();

        if (old('opcional')) {
            $old_opcionais = old('opcional');
        } else {
            $old_opcionais = array();
        }
        // dd($old_opcionais);



        if ($request->tipo_entrada == 2) {
            $contratos = Contrato::where('cod_cliente', $cliente_id)->where('tipo_contrato', 1)->orderby('padrao', 'desc')->get();
            $contratos_padrao = Contrato::where('cod_cliente', 0)->where('tipo_contrato', 1)->orderby('id', 'desc')->get();
        } else if ($request->tipo_entrada == 0) {
            $contratos = Contrato::where('cod_cliente', $cliente_id)->where('tipo_contrato', 2)->orderby('padrao', 'desc')->get();
            $contratos_padrao = Contrato::where('cod_cliente', 0)->where('tipo_contrato', 2)->orderby('id', 'desc')->get();
        } else {
            $contratos = 0;
            $contratos_padrao = 0;
        }

        if ($request->tipo_entrada == 0) {
            $tipo_entrada = "Consignado";
        } else if ($request->tipo_entrada == 1) {
            $tipo_entrada = "Próprio";
        } else {
            $tipo_entrada = "Compra";
        }

        if ($request->tipo_veiculo == 0) {
            $tipo_veiculo = "Carro";
        } else {
            $tipo_veiculo = "Moto";
        }

        if ($request->unico_dono == 0) {
            $unico_dono = "Não";
        } else {
            $unico_dono = "Sim";
        }

        $cores = Cor::get();

        return view('veiculo.form', compact(
            'cores',
            'proprietarios',
            'marcas',
            'marcas_favoritas',
            'modelos',
            'versoes',
            'proprietario',
            'opcionais',
            'tipo_entrada',
            'tipo_veiculo',
            'unico_dono',
            'contratos',
            'contratos_padrao',
            'old_modelo',
            'old_versao',
            'old_opcionais',
            'comprador',
            'request'
        ));
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        if ($request->tipo_entrada == 1) {
            return redirect(route('veiculo.form') . '?tipo_entrada=' . $request->tipo_entrada . '&tipo_veiculo=' . $request->tipo_veiculo . '&unico_dono=' . $request->unico_dono);
        }

        $last_proprietarios = Proprietario::filtroLojista()->orderBy('id', 'desc')->limit(5)->get();

        $proprietarios = Proprietario::filtroLojista()
            ->where('nome_razao', 'LIKE', "%{$request->search}%")
            ->orWhere('cpf_cnpj', 'LIKE', "%{$request->search}%")
            ->orWhere('tel_celular', 'LIKE', "%{$request->search}%")
            ->orWhere('tel_comercial', 'LIKE', "%{$request->search}%")
            // ->orWhere('email', 'LIKE', "%{$searchTerm}%")
            ->get();
        // dd($proprietarios);



        return view('veiculo.create', compact('proprietarios', 'last_proprietarios'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(VeiculoRequest $request)
    {
        //
        if ($request->cod_proprietario) {
            $proprietario = Proprietario::where('id', $request->cod_proprietario)->firstorfail();
            $cod_proprietario =  $proprietario->id;
            $nome_razao =  $proprietario->nome_razao;
        } else {
            $cod_proprietario =  0;
            $nome_razao =  NULL;
        }

        $marca = Marca::where('id', $request->marca_id)->firstorfail();
        $modelo = Modelo::where('id', $request->modelo_id)->firstorfail();
        if ($request->versao_id) {
            $versao = Versao::where('id', $request->versao_id)->first();
            $autozam = $marca->nome . " " . $modelo->nome_modelo . " " . $versao->nome_versao;
        } else {
            $versao = null;
            $autozam = $marca->nome . " " . $modelo->nome_modelo;
        }

        if ($request->valor_consignado) {
            $valor_consignado = str_replace(".", "", $request->valor_consignado);
            $valor_consignado = str_replace(",", ".", $valor_consignado);
        } else {
            $valor_consignado = NULL;
        }

        if ($request->valor_compra) {
            $valor_compra = str_replace(".", "", $request->valor_compra);
            $valor_compra = str_replace(",", ".", $valor_compra);
        } else {
            $valor_compra = NULL;
        }

        $contrato = Contrato::find($request->contrato_id);

        $veiculo = Veiculo::create(array_merge($request->validated(), [
            'autozam' => $autozam,
            'cod_proprietario' => $cod_proprietario,
            'nome_razao' => $nome_razao,
            'tipo_entrada' => $request->tipo_entrada,
            'tipo_veiculo' => $request->tipo_veiculo,
            'unico_dono' => $request->unico_dono,
            'cod_marca' => $request->marca_id,
            'cod_modelo' => $request->modelo_id,
            'cod_versao' => $request->versao_id,
            'placa' => $request->placa,
            'anofabricacao' => $request->anofabricacao,
            'anomodelo' => $request->anomodelo,
            'renavam' => $request->renavam,
            'chassi' => $request->chassi,
            'motor' => $request->numeracaomotor,
            'km' => $request->km,
            'portas' => $request->portas,
            'cor' => $request->cor,
            'combustivel' => $request->combustivel,
            'opcional' => $request->opcional,
            'contrato_id' => $request->contrato_id,
            'ativo' => 2,
            'cod_contrato' => $request->contrato_id,
            'custo' => '0',
            'valor_consignado' => $valor_consignado,
            'valor_compra' => $valor_compra,
            'observacao' => '',
            'cod_cliente' => Auth::user()->cliente_id,
            'estoque' => '1',
            'data_cadastro' => date("Y-m-d H:i:s"),
            'comprado' => 0,
            'condicoes_consignado' => $request->condicoes ?? null,
            'observacao' => $request->txt_observacao,

        ]));


        $entrada = Entrada::create(array_merge($request->validated(), [
            'tipo_entrada' => $request->tipo_entrada,
            'tipo_veiculo' => $request->tipo_veiculo,
            'veiculo_id' => $veiculo->id,
            'lojista_id' => Auth::user()->cliente_id,
            'cliente_id' => $cod_proprietario,
            'contrato_id' => $request->contrato_id,
            'clausulas' => $contrato->clausulas ?? null,
        ]));

        if ($veiculo->tipo_entrada == '0') {
            $tipo_entrada = 'Consignado';
        }
        if ($veiculo->tipo_entrada == '1') {
            $tipo_entrada = 'Próprio';
        }
        if ($veiculo->tipo_entrada == '2') {
            $tipo_entrada = 'Compra';
        }

        $movimento = Movimento::create(array_merge($request->validated(), [
            'lojista_id' => $veiculo->cod_cliente,
            'veiculo_id' => $veiculo->id,
            'autozam' => $autozam,
            'usuario_id' => Auth::user()->id,
            'tipo_movimento' => $tipo_entrada,
        ]));

        if ($request->tipo_entrada > 0) {
            $custo = Custo::create([
                'cod_veiculo' => $veiculo->id,
                'valor_custo' => $valor_compra,
                'tipo_custo' => 6,
                'data_custo' => date("Y-m-d H:i:s"),
                'descricao' => "Cadastro no estoque",
                'custo_visivel' => 0
            ]);
        }


        $json_opcionais = array($request->opcional);
        $json_opcionais = implode(',', array_values($request->opcional));

        $opcionais = OpcionalVeiculo::where('cod_veiculo', $veiculo->id);
        if ($opcionais->count() == 0) {

            OpcionalVeiculo::create(array_merge($request->validated(), [
                'cod_veiculo' => $veiculo->id,
                'opcionais' => $json_opcionais
            ]));
        }
        if ($request->tipo_entrada != 1) {

            return redirect()->route('contrato-veiculo', $veiculo->id);
        } else {
            return redirect()->route('veiculo.imagens', $veiculo->id);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request)
    {

        $rota = Route::getCurrentRoute()->getName();

        $usuario_id = Auth::user()->id;
        $lojista_id = Auth::user()->cliente_id;
        $veiculo = Veiculo::where('id', $request->id)->first();
        // dd($veiculo->cod_cliente);

        if ($veiculo->cod_proprietario > 0) {
            $proprietario = Proprietario::where('id', $veiculo->cod_proprietario)->first();
        } else {

            $proprietario = NULL;
        }


        if ($veiculo->tipo_entrada == 0) {
            $tipo_entrada = "Consignado";
        } else if ($veiculo->tipo_entrada == 1) {
            $tipo_entrada = "Próprio";
        } else {
            $tipo_entrada = "Compra";
        }


        if ($veiculo->tipo_veiculo == 0) {
            $tipo_veiculo = "Carro";
        } else {
            $tipo_veiculo = "Moto";
        }


        if ($veiculo->unico_dono == 0) {
            $unico_dono = "Não";
        } else {
            $unico_dono = "Sim";
        }

        $veiculo->tipo_veiculo == 0 ? $tipo_marca = 0 : $tipo_marca = 1;


        $marcas = Marca::where('tipo_marca', $tipo_marca)->orderBy('nome', 'asc')->get();
        $modelos = Modelo::where('id', '0')->orderBy('nome_modelo', 'asc')->get();
        $versoes = Versao::where('id', '0')->orderBy('nome_versao', 'asc')->get();

        if ((old('modelo_id') ?? $veiculo->cod_modelo) != "") {
            $old_modelo = Modelo::where('id', $veiculo->cod_modelo)->first();
        } else {
            $old_modelo = '';
        }
        if ((old('versao_id') ?? $veiculo->cod_versao) != "") {
            $old_versao = Versao::where('id', $veiculo->cod_versao)->first();
        } else {
            $old_versao = '';
        }


        $opcionais = Opcional::where('tipo_veiculo', $veiculo->tipo_veiculo)->orderBy('opcional', 'asc')->get();

        if ($veiculo->tipo_entrada == 2) {
            // dd($veiculo->cod_contrato);
            $contrato = Contrato::find($veiculo->cod_contrato)->get();
            $contratos = Contrato::where('cod_cliente', $veiculo->cod_cliente)->where('tipo_contrato', 1)->orderby('padrao', 'desc')->get();
            $contratos_padrao = Contrato::where('cod_cliente', 0)->where('tipo_contrato', 1)->orderby('id', 'desc')->get();
        } else if ($veiculo->tipo_entrada == 0) {
            $contrato = Contrato::find($veiculo->cod_contrato)->get();
            $contratos = Contrato::where('cod_cliente', $veiculo->cod_cliente)->where('tipo_contrato', 2)->orderby('padrao', 'desc')->get();
            $contratos_padrao = Contrato::where('cod_cliente', 0)->where('tipo_contrato', 2)->orderby('id', 'desc')->get();
        } else {
            $contrato = 0;
            $contratos = 0;
            $contratos_padrao = 0;
        }

        $opcionais_veiculo = OpcionalVeiculo::where('cod_veiculo', $veiculo->id)->get();

        $opcionais_array = [];
        foreach ($opcionais_veiculo as $o) {
            array_push($opcionais_array, $o['opcionais']);
        }

        $cores = Cor::get();


        return view('veiculo.edit', compact(
            'cores',
            'rota',
            'veiculo',
            'proprietario',
            'tipo_entrada',
            'tipo_veiculo',
            'unico_dono',
            'marcas',
            'modelos',
            'versoes',
            'old_modelo',
            'old_versao',
            'opcionais',
            'contratos',
            'contrato',
            'contratos_padrao',
            'opcionais_array',
            'request'
        ));
    }


    public function venda(Request $request)
    {
        $rota = Route::getCurrentRoute()->getName();

        $usuario_id = Auth::user()->id;
        $lojista_id = Auth::user()->cliente_id;
        $veiculo = Veiculo::where('id', $request->id)->first();

        if ($veiculo->cod_proprietario) {
            $proprietario = Proprietario::where('id', $veiculo->cod_proprietario)->first();
        } else {

            $proprietario = NULL;
        }


        if ($veiculo->tipo_entrada == 0) {
            $tipo_entrada = "Consignado";
        } else if ($veiculo->tipo_entrada == 1) {
            $tipo_entrada = "Próprio";
        } else {
            $tipo_entrada = "Compra";
        }


        if ($veiculo->tipo_veiculo == 0) {
            $tipo_veiculo = "Carro";
        } else {
            $tipo_veiculo = "Moto";
        }


        if ($veiculo->unico_dono == 0) {
            $unico_dono = "Não";
        } else {
            $unico_dono = "Sim";
        }

        $marcas = Marca::where('tipo_marca', $veiculo->tipo_veiculo)->orderBy('nome', 'asc')->get();
        $modelos = Modelo::where('id', '0')->orderBy('nome_modelo', 'asc')->get();
        $versoes = Versao::where('id', '0')->orderBy('nome_versao', 'asc')->get();

        if ((old('modelo_id') ?? $veiculo->cod_modelo) != "") {
            $old_modelo = Modelo::where('id', $veiculo->cod_modelo)->first();
        } else {
            $old_modelo = '';
        }
        if ((old('versao_id') ?? $veiculo->cod_versao) != "") {
            $old_versao = Versao::where('id', $veiculo->cod_versao)->first();
        } else {
            $old_versao = '';
        }


        $opcionais = Opcional::orderBy('opcional', 'asc')->get();

        $contratos = Contrato::where('cod_cliente', $lojista_id)->orderby('id', 'desc')->get();

        $opcionais_veiculo = OpcionalVeiculo::where('cod_veiculo', $veiculo->id)->get();

        $opcionais_array = [];
        foreach ($opcionais_veiculo as $o) {
            array_push($opcionais_array, $o['opcionais']);
        }




        // $opcionais_veiculo = json_decode($opcionais_veiculo->opcionais);
        // if ($opcionais_veiculo) {
        //     $opcionais_veiculo = explode(",", $opcionais_veiculo->opcionais);
        //     // dd($opcionais_veiculo);
        // } else {
        //     $opcionais_veiculo = NULL;
        // }


        return view('veiculo.venda', compact(
            'veiculo',
            'proprietario',
            'tipo_entrada',
            'tipo_veiculo',
            'unico_dono',
            'marcas',
            'modelos',
            'versoes',
            'old_modelo',
            'old_versao',
            'opcionais',
            'contratos',
            'opcionais_array',
            'rota'
        ));
    }

    function custo(Request $request)
    {

        $rota = Route::getCurrentRoute()->getName();
        $usuario_id = Auth::user()->id;
        $lojista_id = Auth::user()->cliente_id;
        $veiculo = Veiculo::where('id', $request->id)->first();


        $fornecedores = Proprietario::query()->filtroLojista()
            ->where('tipo_cliente', 2)
            ->orWhere('tipo_cliente', 3)
            ->paginate(10);

        $custos = custo::where('cod_veiculo', $request->id)
            ->addSelect(['fornecedor1' => function ($query) {
                $query->select('nome_razao')
                    ->from('proprietario')
                    ->whereColumn('id', 'custo.fornecedor')
                    ->limit(1);
            }])->get();

        $custo_total = Custo::where('cod_veiculo', $request->id)->sum('valor_custo');


        return view('veiculo.custo', compact(
            'veiculo',
            'rota',
            'fornecedores',
            'request',
            'custos',
            'custo_total'
        ));
    }

    public function custostore(Request $request)
    {


        if ($request->valor_custo) {
            $valor_custo = str_replace(".", "", $request->valor_custo);
            $valor_custo = str_replace(",", ".", $valor_custo);
        } else {
            $valor_custo = NULL;
        }

        $custo = Custo::create([
            'cod_veiculo' => $request->id,
            'valor_custo' => $valor_custo,
            'tipo_custo' => $request->tipo_custo,
            'data_custo' => $request->data,
            'descricao' => $request->descricao,
            'fornecedor' => $request->fornecedor,
            'custo_visivel' => 0
        ]);

        Session::flash('message', 'Custo cadastrado com sucesso!');
        Session::flash('alert-class', 'alert-success');

        return redirect()->route('veiculo.custo', $request->id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\VeiculoRequest  $request
     * @param  \App\Models\Veiculo  $nft
     * @return \Illuminate\Http\Response
     */
    public function update(VeiculoRequest $request, Veiculo $veiculo)
    {


        if ($request->cod_proprietario) {
            $proprietario = Proprietario::where('id', $request->cod_proprietario)->firstorfail();
            $cod_proprietario =  $proprietario->id;
            $nome_razao =  $proprietario->nome_razao;
        } else {
            $cod_proprietario =  0;
            $nome_razao =  NULL;
        }


        $marca = Marca::where('id', $request->marca_id)->firstorfail();

        $modelo = Modelo::where('id', $request->modelo_id)->firstorfail();

        if ($request->versao_id) {
            $versao = Versao::where('id', $request->versao_id)->first();
            $autozam = $marca->nome . " " . $modelo->nome_modelo . " " . $versao->nome_versao;
        } else {
            $versao = null;
            $autozam = $marca->nome . " " . $modelo->nome_modelo;
        }

        $veiculo = Veiculo::find($request->id);


        if ($request->valor_consignado) {
            $valor_consignado = str_replace(".", "", $request->valor_consignado);
            $valor_consignado = str_replace(",", ".", $valor_consignado);
        } else {
            $valor_consignado = NULL;
        }

        if ($request->valor_compra) {
            $valor_compra = str_replace(".", "", $request->valor_compra);
            $valor_compra = str_replace(",", ".", $valor_compra);
        } else {
            $valor_compra = NULL;
        }
        $veiculo->placa = $request->placa;
        $veiculo->autozam = $autozam;
        $veiculo->cod_proprietario = $cod_proprietario;
        $veiculo->nome_razao = $nome_razao;
        $veiculo->tipo_entrada = $request->tipo_entrada;
        $veiculo->tipo_veiculo = $request->tipo_veiculo;
        $veiculo->unico_dono = $request->unico_dono;
        $veiculo->cod_marca = $request->marca_id;
        $veiculo->cod_modelo = $request->modelo_id;
        $veiculo->cod_versao = $request->versao_id;
        $veiculo->placa = $request->placa;
        $veiculo->anofabricacao = $request->anofabricacao;
        $veiculo->anomodelo = $request->anomodelo;
        $veiculo->renavam = $request->renavam;
        $veiculo->chassi = $request->chassi;
        $veiculo->motor = $request->numeracaomotor;
        $veiculo->km = $request->km;
        $veiculo->portas = $request->portas;
        $veiculo->cor = $request->cor;
        $veiculo->combustivel = $request->combustivel;
        // $veiculo->cod_contrato = $request->contrato_id;
        // $veiculo->ativo = $request->ativo;
        $veiculo->cod_cliente = Auth::user()->cliente_id;
        $veiculo->estoque = '1';
        $veiculo->data_cadastro = date("Y-m-d H:i:s");
        $veiculo->comprado = '0';
        $veiculo->condicoes_consignado = $request->condicoes;
        $veiculo->valor_consignado = $valor_consignado;
        $veiculo->valor_compra = $valor_compra;



        $veiculo->save();


        OpcionalVeiculo::where('cod_veiculo', $request->id)->delete();
        foreach ($request->opcional as $opcional) {
            OpcionalVeiculo::create(array_merge($request->validated(), [
                'cod_veiculo' => $veiculo->id,
                'opcionais' => $opcional
            ]));
        }


        if ($request->visualizar_contrato) {
            return redirect()->route('imprimir-contrato-consignado', $veiculo->id);
        } else {

            return redirect()->route('veiculo.imagens', $veiculo->id);
        }
    }


    public function updateVenda(VeiculoVendaRequest $request, Veiculo $veiculo)
    {
        $valor = str_replace(".", "", $request->valorvenda);
        $valorvenda = str_replace(",", ".", $valor);

        if ($valorvenda <= 1000 && $request->ativo == 1) {
            Session::flash('message', 'Preencha o valor de venda antes de ativar seu anúncio');
            Session::flash('alert-class', 'alert-danger');
            return redirect()->route('veiculo.venda', $request->id);
        }


        Veiculo::where('id', $request->id)->update(['valor' => floatval($valorvenda), 'ativo' => $request->ativo, 'observacao' => $request->txt_observacao]);

        $veiculo = Veiculo::find($request->id);



        if ($request->visualizar_contrato) {
            return redirect()->route('imprimir-contrato-consignado', $request->id);
        } else {

            return redirect()->route('estoque');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function imagens(Request $request)
    {
        $rota = Route::getCurrentRoute()->getName();

        $usuario_id = Auth::user()->id;
        $lojista_id = Auth::user()->cliente_id;
        // $cod_veiculo = $request->id;

        $veiculo = Veiculo::where('id', $request->id)->firstorfail();
        $imagens = Imagem::where('cod_veiculo', $request->id)->get();
        // dd($imagens);

        return view('veiculo.imagens', compact(
            'imagens',
            'veiculo',
            'rota'
        ));
    }

    public function setCapa(Request $request)
    {

        $imagem = Imagem::find($request->id);
        Imagem::where('cod_veiculo', $imagem->cod_veiculo)->update(['capa' => "Não"]);
        Imagem::find($imagem->id)->update(['capa' => "Sim"]);

        return redirect()->back();
    }

    public function excluirImagem(Request $request)
    {

        $imagem = Imagem::find($request->id);
        Storage::delete("/" . $imagem->nome_imagem);
        Imagem::find($imagem->id)->delete();

        return redirect()->back();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeImage(ImagemRequest $request)
    {

        // Define o valor default para a variável que contém o nome da imagem
        $nameFile = null;
        $allowedfileExtension = ['pdf', 'jpg', 'png', 'jpeg', 'gif'];

        $files = $request->file('image');
        foreach ($files as $file) {

            $extension = $file->getClientOriginalExtension();
            $check = in_array($extension, $allowedfileExtension);

            if ($check) {

                $count_imagens = Imagem::where('cod_veiculo', $request->id)->count();


                if ($count_imagens == 0) {
                    $capa = "Sim";
                } else {
                    $capa = "Não";
                }
                $nameFileS3  = Storage::put('/', $file);
                Imagem::create(array_merge($request->validated(), [
                    'nome_imagem' => $nameFileS3,
                    'cod_veiculo' => $request->id,
                    'tipo' => 'Paisagem',
                    'capa' => $capa
                ]));
            }
        }
        return redirect()->route('veiculo.imagens', $request->id);

        // dd($request->file('image'));
        // Verifica se informou o arquivo e se é válido
        // if ($request->hasFile('image') && $request->file('image')->isValid()) {

        //     // // Define um aleatório para o arquivo baseado no timestamps atual
        //     // $name = uniqid(date('HisYmd'));
        //     // // Recupera a extensão do arquivo
        //     // $extension = $request->image->extension();
        //     // // Define finalmente o nome
        //     // $filenametostore = "autobig-{$name}.{$extension}";

        //     // upload para S3



        //     // if ($upload = $request->image->move(public_path('veiculos'), $nameFile)) {
        //     if ($nameFileS3  = Storage::put('/', $request->file('image'))) {
        //         Imagem::create(array_merge($request->validated(), [
        //             'nome_imagem' => $nameFileS3,
        //             'cod_veiculo' => $request->id,
        //             'tipo' => 'Paisagem',
        //             'capa' => 'Não'
        //         ]));
        //         return redirect()->route('veiculo-imagens', $request->id);
        //     } else {
        //         // Verifica se NÃO deu certo o upload (Redireciona de volta)
        //         return redirect()
        //             ->back()
        //             ->with('error', 'Falha ao fazer upload')
        //             ->withInput();
        //     }
        // }
    }


    public function reativar(Request $request)
    {
        $veiculo = Veiculo::where('id', $request->id)->first();
        $valor = str_replace(".", "", $veiculo->valor);
        $valorvenda = str_replace(",", ".", $valor);

        if ($valorvenda <= 1000) {
            Session::flash('message', 'Preencha o valor de venda para ativar seu anúncio');
            Session::flash('alert-class', 'alert-danger');
            return redirect()->route('estoque');
        }

        $veiculo = Veiculo::where('id', $request->id)->update(['ativo' => 1]);



        return redirect()->route('estoque');
    }

    public function pausar(Request $request)
    {
        Veiculo::where('id', $request->id)->update(['ativo' => 2]);
        return redirect()->route('estoque');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }


    public function maisvistos(Request $request)
    {
        $cliente_id = Auth::user()->cliente_id;
        $lojistas = Lojista::get();

        $search = $request->search;
        $lojista_id = $request->lojista_id;

        $veiculos = Veiculo::query()
            ->filtroLojista()
            ->addSelect(['imagem' => function ($query) {
                $query->select('nome_imagem')
                    ->from('imagem')
                    ->whereColumn('cod_veiculo', 'veiculo.id')
                    ->where('capa', 'Sim')
                    ->limit(1);
            }])
            ->where(function ($query) use ($search) {
                $query->where('autozam', 'LIKE', '%' . $search . '%')
                    ->orWhere('placa', 'LIKE', '%' . $search . '%')
                    ->orWhere('renavam', 'LIKE', '%' . $search . '%');
            })
            ->where(function ($query) use ($lojista_id) {
                $query->where('cod_cliente', 'LIKE', '%' . $lojista_id . '%');
            })
            ->where('venda', 0)
            ->orderby('tc', 'desc')
            ->paginate(10);

        return view('veiculo.maisvistos', compact('veiculos', 'lojistas', 'request'));
    }
}
