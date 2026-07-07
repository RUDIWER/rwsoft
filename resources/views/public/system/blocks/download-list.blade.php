@php
    $locale = $site['current_locale'] ?? null;
    $downloads = collect($block['downloads'] ?? [])->filter(fn ($download) => is_array($download));
    $lockedFolders = collect($block['locked_folders'] ?? [])->filter(fn ($folder) => is_array($folder));
@endphp

<section class="rw-public-block rw-public-download-list">
    @if (! empty($block['title']))
        <h2 class="rw-public-block__title">{{ $block['title'] }}</h2>
    @endif

    @if ($lockedFolders->isNotEmpty())
        <div class="rw-public-download-list__locked-folders">
            @foreach ($lockedFolders as $folder)
                <form class="rw-public-download-list__unlock" method="post" action="{{ $folder['unlock_url'] }}">
                    @csrf
                    <h3 class="rw-public-download-list__title">{{ $folder['name'] }}</h3>
                    <p>{{ public_text('downloads.folder_locked', 'This folder is password protected.', $locale) }}</p>
                    <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                    <label class="rw-public-form-label" for="download-folder-password-{{ $folder['id'] }}">
                        {{ public_text('downloads.password_label', 'Password', $locale) }}
                    </label>
                    <input id="download-folder-password-{{ $folder['id'] }}" class="rw-public-form-input" type="password" name="password" autocomplete="current-password" required>
                    <button class="rw-public-button rw-public-button--primary" type="submit">
                        {{ public_text('downloads.unlock_folder', 'Unlock folder', $locale) }}
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    @if ($downloads->isNotEmpty())
        <div class="rw-public-download-list__items" role="list">
            @foreach ($downloads as $download)
                <article class="rw-public-download-list__item" role="listitem">
                    <div class="rw-public-download-list__body">
                        <h3 class="rw-public-download-list__title">{{ $download['title'] }}</h3>
                        @if (! empty($block['show_descriptions']) && ! empty($download['description']))
                            <p>{{ $download['description'] }}</p>
                        @endif
                        <p class="rw-public-post-meta">
                            {{ strtoupper((string) ($download['extension'] ?? '')) }}
                            @if (! empty($download['size_kb']))
                                · {{ $download['size_kb'] }} KB
                            @endif
                        </p>
                    </div>
                    <a class="rw-public-button" href="{{ $download['download_url'] }}">
                        {{ public_text('downloads.download_button', 'Download', $locale) }}
                    </a>
                </article>
            @endforeach
        </div>
    @elseif ($lockedFolders->isEmpty())
        <p class="rw-public-block__text">{{ public_text('downloads.empty', 'No downloads are available.', $locale) }}</p>
    @endif
</section>
