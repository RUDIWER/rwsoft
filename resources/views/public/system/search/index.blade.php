@extends('public.system.layouts.sitebuilder')

@section('content')
    <div class="rw-public-page rw-public-page--width-content rw-public-page--gap-normal">
        <section class="rw-public-section rw-public-section--width-content rw-public-section--layout-standard rw-public-section--spacing-normal">
            <div class="rw-public-section__inner">
                @include('public.system.blocks.site-search', [
                    'block' => ['runtime_id' => 'fallback-search'],
                    'search' => $search ?? [],
                ])
            </div>
        </section>
    </div>
@endsection
