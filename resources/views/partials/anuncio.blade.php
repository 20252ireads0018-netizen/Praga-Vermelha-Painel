{{-- Faixa de anúncio (AdSense) do plano grátis, num bloco fixo (ADSENSE_SLOT). Sem o bloco, quem escolhe onde mostrar
     são os anúncios automáticos do AdSense (o script do <head> do layout). --}}
@php
    $adsCliente = (string) config('planos.adsense_cliente');
    $adsSlot    = (string) config('planos.adsense_slot');
@endphp
@if ($adsCliente !== '' && $adsSlot !== '' && \App\Support\Plano::veAnuncios(auth()->user()))
    <aside class="pv-anuncio" aria-label="Publicidade">
        <span class="pv-anuncio__rotulo">Publicidade</span>
        <ins class="adsbygoogle" style="display:block"
             data-ad-client="{{ $adsCliente }}"
             data-ad-slot="{{ $adsSlot }}"
             data-ad-format="auto" data-full-width-responsive="true"></ins>
        <script>(window.adsbygoogle = window.adsbygoogle || []).push({});</script>
        <span class="pv-anuncio__sem"><a href="{{ route('planos.index') }}">Tire os anúncios para sempre com o livro do sistema</a></span>
    </aside>
@endif
