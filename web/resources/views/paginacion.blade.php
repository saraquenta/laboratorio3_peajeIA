@if ($paginator->hasPages())
<nav>
    @if ($paginator->onFirstPage())<span class="btn chico sec" style="opacity:.5">← Anterior</span>
    @else<a class="btn chico sec" href="{{ $paginator->previousPageUrl() }}">← Anterior</a>@endif
    <span style="color:var(--suave)">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }} · {{ $paginator->total() }} registros</span>
    @if ($paginator->hasMorePages())<a class="btn chico sec" href="{{ $paginator->nextPageUrl() }}">Siguiente →</a>
    @else<span class="btn chico sec" style="opacity:.5">Siguiente →</span>@endif
</nav>
@endif
