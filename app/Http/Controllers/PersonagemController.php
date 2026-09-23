<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Personagem;

class PersonagemController extends Controller
{
    public function home(){
            return view('dashboard.home');
    }

    public function fichas(){
        return view('personagem.form');
    }

        public function fichafeita(Request $request)
    {
        $dados = $this->validarFicha($request);
        $personagem = Personagem::create($dados);

        return redirect()->route('personagens.show', $personagem->id);
    }

    public function update(Request $request, string $id)
    {
        $personagem = Personagem::findOrFail($id);
        $dados = $this->validarFicha($request);
        $personagem->update($dados);

        return redirect()->route('personagens.show', $personagem->id)
            ->with('sucesso', 'Ficha atualizada com sucesso!');
    }

    private function validarFicha(Request $request): array
    {
        $dados = $request->validate([
            'nome' => 'required|string|max:255',
            'raca' => 'required|string',
            'maldicoes' => 'nullable|string',
            'idade' => 'required|integer',
            'profissao' => 'nullable|string',
            'nivel_mundano' => 'nullable|integer',
            'experiencia_mundana' => 'nullable|integer',
            'porte_fisico' => 'nullable|string',
            'be' => 'nullable|integer',
            'forca' => 'nullable|integer',
            'constituicao' => 'nullable|integer',
            'destreza' => 'nullable|integer',
            'conhecimento' => 'nullable|integer',
            'carisma' => 'nullable|integer',
            'percepcao' => 'nullable|integer',
            'alma' => 'nullable|integer',
            'sorte' => 'nullable|integer',
            'descricao' => 'nullable|string',
            'vida_atual' => 'nullable|integer',
            'vida_maxima' => 'nullable|integer',
            'sanidade_atual' => 'nullable|integer',
            'sanidade_maxima' => 'nullable|integer',
            'aura_atual' => 'nullable|integer',
            'aura_maxima' => 'nullable|integer',
        ]);

        $nomesHabilidades = [
            'luta', 'resiliencia', 'acrobacia', 'atletismo', 'agarrar', 'arremesso',
            'desviar', 'contra_ataque', 'bloquear', 'iniciativa', 'furtividade',
            'enxergar', 'olfato', 'audicao', 'maldicao_hab', 'precisao', 'investigacao',
            'sobrevivencia', 'construcao', 'procura', 'intuicao', 'tecnologia', 'religiao',
            'raciocinio', 'medicina', 'primeiros_socorros', 'culinaria', 'dirigir',
            'explosivos', 'fotografia', 'labia', 'barganhar', 'manipulacao', 'intimidar',
            'arte', 'disfarce', 'performance', 'detectar_mentira',
        ];

        $habilidades = [];
        foreach ($nomesHabilidades as $habilidade) {
            $habilidades[$habilidade] = $request->input($habilidade, 0);
        }

        $dados['habilidades'] = $habilidades;
        $dados['usuario_id'] = auth()->id() ?? null;

        return $dados;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $personagens = Personagem::orderBy('created_at', 'desc')->get();
        return view('personagem.lista', compact('personagens'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $personagem = Personagem::findOrFail($id);
        return view('personagem.form', compact('personagem'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $personagem = Personagem::findOrFail($id);
        $personagem->delete();

        return redirect()->route('personagens.index')->with('sucesso', 'Ficha excluida com sucesso!');
        
    }
}