@extends('layout')

@section('title', 'Comprar o livro do sistema — Praga Vermelha')

@section('conteudo')
<section class="pv-section">
    <div class="pv-container" style="max-width:520px">
        <span class="pv-eyebrow">Livro do sistema · {{ $preco }}</span>
        <h1 class="pv-section__title">Seus dados para o pagamento</h1>
        <p class="pv-section__desc">O emissor da cobrança (Asaas) exige CPF ou CNPJ. Depois você escolhe Pix, boleto ou cartão.</p>

        <form method="POST" action="{{ route('livro.compra.dados.salvar') }}" class="pl-plano" style="margin-top:20px">
            @csrf
            <label for="cpf_cnpj">CPF ou CNPJ</label>
            <div class="pl-chave">
                <input type="text" id="cpf_cnpj" name="cpf_cnpj" value="{{ old('cpf_cnpj', $documento) }}" inputmode="numeric"
                       autocomplete="off" maxlength="20" placeholder="000.000.000-00" required autofocus>
            </div>
            @error('cpf_cnpj')
                <small class="pl-erro">{{ $message }}</small>
            @enderror
            <button type="submit" class="pv-btn pv-btn--primary">Ir para o pagamento</button>
            <a href="{{ route('planos.index') }}" class="pv-btn pv-btn--ghost">Voltar</a>
        </form>
    </div>
</section>
@endsection
