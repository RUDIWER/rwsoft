<?php

return [
    'text' => <<<'CSS'
.rw-public-block--text {
    display: grid;
    gap: clamp(1rem, 2vw, 1.35rem);
}
CSS,

    'feature_card' => <<<'CSS'
.rw-public-block--feature-card {
    position: relative;
    isolation: isolate;
    display: grid;
    align-content: start;
    gap: clamp(1rem, 2vw, 1.35rem);
    min-height: 100%;
}

.rw-public-feature-card__accent {
    width: 3.35rem;
    height: 0.32rem;
    border-radius: 999px;
    background: linear-gradient(
        90deg,
        var(--rw-public-color-primary),
        color-mix(in srgb, var(--rw-public-color-primary) 28%, transparent)
    );
}

.rw-public-feature-card__title {
    margin-top: 0;
}

.rw-public-block--feature-card > .rw-public-block-slot--media {
    margin: 0;
}

.rw-public-block--feature-card > .rw-public-block-slot--media .rw-public-placement {
    width: 100%;
}

.rw-public-block--feature-card > .rw-public-block-slot--media .rw-public-block--image {
    border: 0;
    border-radius: 0;
    box-shadow: none;
}

.rw-public-block--feature-card > .rw-public-block-slot--media .rw-public-image {
    aspect-ratio: 16 / 9;
    object-fit: cover;
}
CSS,

    'quote' => <<<'CSS'
.rw-public-quote {
    position: relative;
    border-left: 0;
    padding-left: clamp(1.65rem, 3vw, 2.25rem);
}

.rw-public-quote::before {
    position: absolute;
    top: -0.3rem;
    left: 0;
    color: color-mix(in srgb, var(--rw-public-color-primary) 34%, transparent);
    content: '\201C';
    font-size: clamp(3rem, 8vw, 5rem);
    font-weight: 900;
    line-height: 1;
}

.rw-public-quote__text {
    margin: 0;
    font-size: clamp(1.35rem, 3vw, 2rem);
    font-weight: 750;
    line-height: 1.45;
}

.rw-public-quote__source {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-top: 0.8rem;
    color: var(--rw-public-color-muted);
}

.rw-public-quote__source::before {
    display: inline-block;
    width: 1.6rem;
    height: 2px;
    border-radius: 999px;
    background: currentColor;
    content: '';
}
CSS,

    'testimonial' => <<<'CSS'
.rw-public-block--testimonial {
    display: grid;
    align-content: start;
}

.rw-public-testimonial__quote {
    display: grid;
    gap: 1rem;
    margin: 0;
}

.rw-public-testimonial__text {
    margin: 0;
    color: var(--rw-public-color-text);
    font-size: clamp(1.2rem, 2.6vw, 1.75rem);
    font-weight: 750;
    line-height: 1.5;
}

.rw-public-testimonial__text::before {
    color: var(--rw-public-color-primary);
    content: '\201C';
}

.rw-public-testimonial__text::after {
    color: var(--rw-public-color-primary);
    content: '\201D';
}

.rw-public-testimonial__source {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--rw-public-color-muted);
    font-weight: 800;
}

.rw-public-testimonial__source::before {
    display: inline-block;
    width: 1.6rem;
    height: 2px;
    border-radius: 999px;
    background: currentColor;
    content: '';
}
CSS,

    'stats' => <<<'CSS'
.rw-public-block--stats {
    display: grid;
    gap: 0.55rem;
    align-content: center;
    min-height: 100%;
    overflow: visible;
    text-align: center;
}

.rw-public-stats__value {
    display: inline-flex;
    align-items: baseline;
    justify-content: center;
    margin: 0;
    color: var(--rw-public-color-primary);
    font-size: clamp(2.7rem, 8vw, 5rem);
    font-weight: 900;
    letter-spacing: -0.07em;
    line-height: 0.95;
}

.rw-public-stats__suffix {
    margin-left: 0.15em;
    font-size: 0.42em;
    letter-spacing: -0.035em;
}

.rw-public-stats__label {
    max-width: 18rem;
    margin: 0 auto;
    color: var(--rw-public-color-muted);
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.5;
}
CSS,

    'image' => <<<'CSS'
.rw-public-block--image {
    overflow: visible;
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.rw-public-block--image figure {
    margin: 0;
}

.rw-public-image {
    display: block;
    width: 100%;
    height: auto;
}

.rw-public-image-caption {
    margin: 0;
    padding: 0.85rem 1.25rem 1.1rem;
    color: var(--rw-public-color-muted);
    font-size: 0.92rem;
}
CSS,

    'video' => <<<'CSS'
.rw-public-block--video {
    display: grid;
    gap: clamp(1rem, 2vw, 1.35rem);
    padding: 0;
    overflow: visible;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.rw-public-video__title {
    padding-inline: 0.1rem;
}

.rw-public-video__frame {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--rw-public-color-border);
    border-radius: 1.25rem;
    background: #000;
    box-shadow: var(--rw-public-shadow-soft);
    transition:
        box-shadow 180ms ease,
        transform 180ms ease;
    aspect-ratio: 16 / 9;
}

.rw-public-video__frame:hover {
    box-shadow: 0 30px 90px rgb(15 23 42 / 16%);
    transform: translateY(-2px);
}

.rw-public-video__frame iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
}
CSS,

    'button' => <<<'CSS'
.rw-public-button--block {
    justify-content: center;
}
CSS,

    'site_button' => <<<'CSS'
.rw-public-site-button {
    justify-content: center;
}
CSS,

    'accordion' => <<<'CSS'
.rw-public-block--accordion,
.rw-public-block--faq {
    display: grid;
    gap: 1rem;
}

.rw-public-accordion__trigger {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border: 1px solid var(--rw-public-color-border);
    border-radius: 1rem;
    background: color-mix(in srgb, var(--rw-public-color-surface) 94%, var(--rw-public-color-primary) 6%);
    color: var(--rw-public-color-text);
    cursor: pointer;
    font: inherit;
    font-weight: 850;
    padding: 0.95rem 1rem;
    text-align: left;
}

.rw-public-accordion__trigger::after {
    flex: 0 0 auto;
    color: var(--rw-public-color-primary);
    content: '+';
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1;
}

.rw-public-accordion__trigger[aria-expanded='true'] {
    border-color: color-mix(in srgb, var(--rw-public-color-primary) 42%, var(--rw-public-color-border) 58%);
    background: color-mix(in srgb, var(--rw-public-color-surface) 84%, var(--rw-public-color-primary) 16%);
}

.rw-public-accordion__trigger[aria-expanded='true']::after {
    content: '-';
}

.rw-public-accordion__trigger:hover,
.rw-public-accordion__trigger:focus-visible {
    border-color: var(--rw-public-color-primary);
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rw-public-color-primary) 18%, transparent);
}

.rw-public-accordion__panel {
    border: 1px solid color-mix(in srgb, var(--rw-public-color-border) 72%, transparent);
    border-top: 0;
    border-radius: 0 0 1rem 1rem;
    margin-top: -1rem;
    padding: 1rem;
}
CSS,

    'faq' => <<<'CSS'
.rw-public-block--faq {
    display: grid;
    gap: 1rem;
}
CSS,

    'tabs' => <<<'CSS'
.rw-public-block--tabs {
    display: grid;
    gap: 1rem;
}

.rw-public-tabs__list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    border-bottom: 1px solid var(--rw-public-color-border);
}

.rw-public-tabs__list button {
    border: 0;
    border-bottom: 3px solid transparent;
    background: transparent;
    color: var(--rw-public-color-muted);
    cursor: pointer;
    font: inherit;
    font-weight: 850;
    margin-bottom: -1px;
    padding: 0.75rem 0.25rem;
}

.rw-public-tabs__list button[aria-selected='true'] {
    border-bottom-color: var(--rw-public-color-primary);
    color: var(--rw-public-color-primary);
}

.rw-public-tabs__list button:hover,
.rw-public-tabs__list button:focus-visible {
    border-color: var(--rw-public-color-primary);
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rw-public-color-primary) 18%, transparent);
}

.rw-public-block--tabs [role='tabpanel'] {
    padding-top: 0.45rem;
}
CSS,

    'carousel' => <<<'CSS'
.rw-public-block--carousel {
    position: relative;
    display: grid;
    gap: 0.85rem;
}

.rw-public-carousel__slide {
    min-height: 10rem;
    border: 1px solid color-mix(in srgb, var(--rw-public-color-primary) 18%, var(--rw-public-color-border) 82%);
    border-radius: 1.15rem;
    background: linear-gradient(145deg, var(--rw-public-color-surface), color-mix(in srgb, var(--rw-public-color-surface) 86%, var(--rw-public-color-primary) 14%));
    padding: clamp(1.1rem, 3vw, 1.6rem);
}

.rw-public-carousel__previous,
.rw-public-carousel__next {
    min-height: 2.45rem;
    border: 1px solid var(--rw-public-color-border);
    border-radius: 999px;
    background: var(--rw-public-color-surface);
    color: var(--rw-public-color-text);
    cursor: pointer;
    font: inherit;
    font-size: 0.9rem;
    font-weight: 800;
    padding-inline: 0.9rem;
}

.rw-public-carousel__previous:hover,
.rw-public-carousel__previous:focus-visible,
.rw-public-carousel__next:hover,
.rw-public-carousel__next:focus-visible {
    border-color: var(--rw-public-color-primary);
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rw-public-color-primary) 18%, transparent);
}

.rw-public-carousel__previous {
    justify-self: start;
}

.rw-public-carousel__next {
    justify-self: end;
    margin-top: -3.3rem;
}

@media (min-width: 640px) {
    .rw-public-block--carousel {
        grid-template-columns: auto 1fr auto;
        align-items: center;
    }

    .rw-public-carousel__slide {
        grid-column: 1 / -1;
    }

    .rw-public-carousel__previous,
    .rw-public-carousel__next {
        margin-top: 0;
    }
}
CSS,

    'steps' => <<<'CSS'
.rw-public-block--steps,
.rw-public-block--icon-list {
    display: grid;
    gap: 1rem;
}

.rw-public-steps,
.rw-public-icon-list {
    display: grid;
    gap: 0.85rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.rw-public-steps {
    counter-reset: rw-public-step;
}

.rw-public-steps__item,
.rw-public-icon-list__item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.85rem;
    align-items: start;
    border: 1px solid var(--rw-public-color-border);
    border-radius: 1rem;
    padding: 1rem;
}

.rw-public-steps__item {
    counter-increment: rw-public-step;
}

.rw-public-steps__item::before,
.rw-public-icon-list__marker {
    display: inline-grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    border-radius: 999px;
    background: var(--rw-public-color-primary);
    color: var(--rw-public-color-primary-contrast);
    font-size: 0.82rem;
    font-weight: 900;
}

.rw-public-steps__item::before {
    content: counter(rw-public-step);
}

.rw-public-icon-list__marker::before {
    content: '';
    width: 0.55rem;
    height: 0.55rem;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(45deg) translate(-1px, -1px);
}

.rw-public-icon-list__item strong {
    display: block;
    color: var(--rw-public-color-text);
}

.rw-public-icon-list__item .rw-public-block__text {
    display: block;
}
CSS,

    'icon_list' => <<<'CSS'
.rw-public-block--icon-list,
.rw-public-icon-list {
    display: grid;
    gap: 0.85rem;
}
CSS,

    'logo_strip' => <<<'CSS'
.rw-public-block--logo-strip {
    display: grid;
    gap: 1.2rem;
    padding: clamp(1.2rem, 3vw, 1.8rem);
}

.rw-public-logo-strip__title {
    text-align: center;
}

.rw-public-logo-strip__list {
    display: flex;
    flex-wrap: wrap;
    gap: clamp(0.9rem, 2vw, 1.5rem);
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 0;
    list-style: none;
}

.rw-public-logo-strip__item {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 7rem;
    min-height: 4.25rem;
    border: 1px solid var(--rw-public-color-border);
    border-radius: 1rem;
    background: color-mix(in srgb, var(--rw-public-color-surface) 92%, var(--rw-public-color-primary) 8%);
    padding: 0.85rem 1rem;
    transition:
        border-color 160ms ease,
        transform 160ms ease;
}

.rw-public-logo-strip__item:hover {
    border-color: color-mix(in srgb, var(--rw-public-color-primary) 38%, var(--rw-public-color-border) 62%);
    transform: translateY(-1px);
}

.rw-public-logo-strip__image {
    display: block;
    width: auto;
    max-width: min(9rem, 42vw);
    height: auto;
    max-height: 3.5rem;
    object-fit: contain;
}
CSS,

    'breadcrumb' => <<<'CSS'
.rw-public-breadcrumb {
    padding: 0;
    overflow: visible;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.rw-public-breadcrumb--compact {
    font-size: 0.88rem;
}

.rw-public-breadcrumb__list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    align-items: center;
    margin: 0;
    padding: 0;
    color: var(--rw-public-color-muted);
    list-style: none;
}

.rw-public-breadcrumb__item {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.rw-public-breadcrumb__separator {
    color: var(--rw-public-color-border);
    flex: 0 0 auto;
}

.rw-public-breadcrumb__link,
.rw-public-breadcrumb__current {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.rw-public-breadcrumb__link {
    color: inherit;
    font-weight: 700;
    text-decoration: none;
}

.rw-public-breadcrumb__link:hover,
.rw-public-breadcrumb__link:focus-visible {
    color: var(--rw-public-color-primary);
    outline: 0;
}

.rw-public-breadcrumb__current {
    color: var(--rw-public-color-text);
    font-weight: 800;
}

.rw-public-breadcrumb .mdi {
    flex: 0 0 auto;
    line-height: 1;
}
CSS,

    'rich_text' => <<<'CSS'
.rw-public-block--rich-text {
    display: grid;
    gap: clamp(1rem, 2vw, 1.35rem);
    overflow: visible;
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.rw-public-block--rich-text::before,
.rw-public-block--rich-text::after {
    display: none;
    content: none;
}
CSS,

    'markdown_text' => <<<'CSS'
.rw-public-block--markdown-text {
    display: grid;
    gap: clamp(1rem, 2vw, 1.35rem);
    overflow: visible;
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.rw-public-block--markdown-text::before,
.rw-public-block--markdown-text::after {
    display: none;
    content: none;
}
CSS,

    'site_raw_html' => <<<'CSS'
.rw-public-raw-html,
.rw-public-html {
    overflow: visible;
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.rw-public-raw-html > :first-child {
    margin-block-start: 0;
}

.rw-public-raw-html > :last-child {
    margin-block-end: 0;
}
CSS,

    'address_block' => <<<'CSS'
.rw-public-address {
    display: grid;
    gap: 1rem;
    align-content: start;
    overflow: visible;
}

.rw-public-address__media {
    margin: 0;
}

.rw-public-address__media img {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
    object-fit: cover;
}

.rw-public-address__content {
    display: grid;
    gap: 0.65rem;
    padding: 0;
}

.rw-public-address__title {
    margin: 0;
    font-size: 1.5em;
    font-weight: 700;
    line-height: 1.2;
}

.rw-public-address__company {
    margin: 0;
    font-weight: 850;
}

.rw-public-address__content > *,
.rw-public-address__address,
.rw-public-address__list,
.rw-public-address__custom-fields {
    margin: 0;
}

.rw-public-address__address,
.rw-public-address__list,
.rw-public-address__custom-fields {
    display: grid;
    gap: 0.35rem;
}

.rw-public-address__list {
    padding: 0;
    list-style: none;
}

.rw-public-address__list li,
.rw-public-address__vat,
.rw-public-address__custom-fields > div {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.25rem 0.45rem;
}

.rw-public-address__label,
.rw-public-address__custom-fields dt {
    color: inherit;
    font: inherit;
    font-weight: 700;
}

.rw-public-address__label::after,
.rw-public-address__custom-fields dt::after {
    content: ':';
}

.rw-public-address__custom-fields dd {
    margin: 0;
}

.rw-public-address a {
    color: var(--rw-public-color-primary);
    font-weight: 750;
    text-decoration-thickness: 0.08em;
    text-underline-offset: 0.18em;
}

@media (min-width: 760px) {
    .rw-public-address--image-left,
    .rw-public-address--image-right {
        grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
        align-items: stretch;
    }

    .rw-public-address--image-right .rw-public-address__media {
        order: 2;
    }

    .rw-public-address--image-left .rw-public-address__media img,
    .rw-public-address--image-right .rw-public-address__media img {
        height: 100%;
        min-height: 18rem;
    }
}
CSS,

    'site_menu' => <<<'CSS'
.rw-public-menu-block {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.rw-public-menu-block__title {
    margin: 0;
    font-size: 1.5em;
    font-weight: 700;
    line-height: 1.2;
}
CSS,

    'download_list' => <<<'CSS'
.rw-public-download-list,
.rw-public-block.rw-public-download-list {
    display: grid;
    gap: clamp(1rem, 2vw, 1.35rem);
    overflow: visible;
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.rw-public-download-list__items,
.rw-public-download-list__locked-folders {
    display: grid;
    gap: clamp(0.75rem, 2vw, 1rem);
}

.rw-public-download-list__item,
.rw-public-download-list__unlock {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0;
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}

.rw-public-download-list__unlock {
    align-items: flex-start;
    flex-wrap: wrap;
}

.rw-public-download-list__body {
    display: grid;
    gap: 0.35rem;
    min-width: 0;
}

.rw-public-download-list__title {
    margin: 0;
    font-size: clamp(1.05rem, 2vw, 1.25rem);
    line-height: 1.25;
}

.rw-public-download-list__item .rw-public-post-meta {
    margin: 0;
}

.rw-public-download-list__item .rw-public-button {
    flex-shrink: 0;
}

.rw-public-download-list::before,
.rw-public-download-list::after,
.rw-public-block.rw-public-download-list::before,
.rw-public-block.rw-public-download-list::after {
    display: none;
    content: none;
}

@media (max-width: 640px) {
    .rw-public-download-list__item,
    .rw-public-download-list__unlock {
        align-items: stretch;
        flex-direction: column;
    }
}
CSS,

    'download_browser' => <<<'CSS'
.rw-public-placement[data-cms-renderer='download_browser'] {
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}
CSS,

    'list_rows' => <<<'CSS'
.rw-public-content-list {
    display: grid;
    gap: clamp(1rem, 2vw, 1.35rem);
}

.rw-public-content-list__search {
    display: grid;
    gap: 0.45rem;
    margin-top: 1rem;
}

.rw-public-content-list__search-label {
    color: var(--rw-public-color-muted);
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.rw-public-content-list__search-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    align-items: center;
}

.rw-public-content-list__search-input {
    flex: 1 1 16rem;
    min-height: 2.35rem;
    border: 1px solid var(--rw-public-color-border);
    border-radius: 999px;
    background: var(--rw-public-color-surface);
    color: var(--rw-public-color-text);
    padding-inline: 0.95rem;
    outline: none;
}

.rw-public-content-list__search-input:focus {
    border-color: var(--rw-public-color-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--rw-public-color-primary) 18%, transparent);
}

.rw-public-content-list__search-reset {
    color: var(--rw-public-color-muted);
    font-size: 0.9rem;
    font-weight: 700;
    text-decoration: none;
}

.rw-public-content-list__search-reset:hover,
.rw-public-content-list__search-reset:focus-visible {
    color: var(--rw-public-color-primary);
}

.rw-public-content-list__rows {
    display: grid;
    gap: 0.75rem;
}

.rw-public-content-list__row {
    display: grid;
    gap: 0.4rem;
    border: 1px solid var(--rw-public-color-border);
    border-radius: var(--rw-public-radius-md);
    background: var(--rw-public-color-surface);
    color: inherit;
    padding: 1rem 1.1rem;
    text-decoration: none;
    transition:
        border-color 160ms ease,
        box-shadow 160ms ease,
        transform 160ms ease;
}

.rw-public-content-list__row:hover,
.rw-public-content-list__row:focus-visible {
    border-color: var(--rw-public-color-primary);
    outline: none;
    box-shadow: var(--rw-public-shadow-card);
    transform: translateY(-1px);
}

.rw-public-content-list__row-title {
    color: var(--rw-public-color-text);
    font-size: 1.05rem;
    font-weight: 850;
}

.rw-public-content-list__row-excerpt {
    color: var(--rw-public-color-muted);
    line-height: 1.6;
}
CSS,

    'list_grid' => <<<'CSS'
.rw-public-content-list {
    display: grid;
    gap: clamp(1rem, 2vw, 1.35rem);
}
CSS,

    'dynamic_field' => <<<'CSS'
.rw-public-block--dynamic-field {
    overflow: visible;
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.rw-public-block--dynamic-field::before,
.rw-public-block--dynamic-field::after {
    display: none;
    content: none;
}

.rw-public-placement[data-cms-renderer='dynamic_field'] {
    border: 0;
    border-inline-start: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}
CSS,

    'site_logo' => <<<'CSS'
.rw-public-logo {
    align-items: center;
    color: inherit;
    display: inline-flex;
    font-weight: 700;
    gap: 0.5rem;
    text-decoration: none;
}

.rw-public-logo img {
    display: block;
    max-height: 3rem;
    width: auto;
}
CSS,

    'site_brand' => <<<'CSS'
.rw-public-logo {
    align-items: center;
    color: inherit;
    display: inline-flex;
    font-weight: 700;
    gap: 0.5rem;
    text-decoration: none;
}
CSS,

    'site_baseline' => <<<'CSS'
.rw-public-baseline {
    margin: 0;
}
CSS,

    'site_login' => <<<'CSS'
.rw-public-login {
    color: inherit;
    text-decoration: none;
}
CSS,

    'site_link' => <<<'CSS'
.rw-public-link,
.rw-public-site-link {
    color: var(--rw-public-color-primary);
    font-weight: 800;
    text-underline-offset: 0.22em;
}

.rw-public-link:hover,
.rw-public-link:focus-visible,
.rw-public-site-link:hover,
.rw-public-site-link:focus-visible {
    color: var(--rw-public-color-primary-strong);
}
CSS,

    'site_promo' => <<<'CSS'
.rw-public-promo {
    align-items: center;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin: 0;
}

.rw-public-promo a {
    color: inherit;
    font-weight: 700;
}
CSS,

    'site_search' => <<<'CSS'
.rw-public-search {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.rw-public-search__form {
    display: grid;
    gap: 0.5rem;
}

.rw-public-search__label {
    font-size: var(--rw-public-font-size-small);
    font-weight: 700;
    color: var(--rw-public-color-muted);
}

.rw-public-search__control {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.rw-public-search__input {
    min-width: min(100%, 20rem);
    flex: 1 1 18rem;
    border: 1px solid var(--rw-public-color-border);
    border-radius: var(--rw-public-radius-sm);
    background: var(--rw-public-color-surface);
    color: var(--rw-public-color-text);
    font: inherit;
    padding: 0.85rem 1rem;
}

.rw-public-search__button {
    flex: 0 0 auto;
}

.rw-public-search__meta,
.rw-public-search__snippet,
.rw-public-search__empty {
    margin: 0;
    color: var(--rw-public-color-muted);
}

.rw-public-search__results {
    display: grid;
    gap: 0.75rem;
}

.rw-public-search__result {
    border: 1px solid var(--rw-public-color-border);
    border-radius: var(--rw-public-radius-md);
    background: var(--rw-public-color-surface);
    padding: 1rem;
}

.rw-public-search__result-title {
    margin: 0 0 0.35rem;
    font-family: var(--rw-public-font-heading);
    font-size: clamp(1.1rem, 2vw, 1.35rem);
}
CSS,

    'docs_search' => <<<'CSS'
.rw-public-search {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.rw-public-search__form {
    display: grid;
    gap: 0.5rem;
}

.rw-public-search__label {
    font-size: var(--rw-public-font-size-small);
    font-weight: 700;
    color: var(--rw-public-color-muted);
}

.rw-public-search__control {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.rw-public-search__input {
    min-width: min(100%, 20rem);
    flex: 1 1 18rem;
    border: 1px solid var(--rw-public-color-border);
    border-radius: var(--rw-public-radius-sm);
    background: var(--rw-public-color-surface);
    color: var(--rw-public-color-text);
    font: inherit;
    padding: 0.85rem 1rem;
}
CSS,

    'site_language_switcher' => <<<'CSS'
.rw-public-language-switcher,
.rw-public-language-menu {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.rw-public-language-switcher a,
.rw-public-language-menu a {
    color: inherit;
    text-decoration: none;
}

.rw-public-language-switcher a.is-active {
    font-weight: 800;
    text-decoration: underline;
    text-underline-offset: 0.25em;
}

.rw-public-language-menu {
    --rw-public-language-gap: 0.35rem;
    color: var(--rw-public-language-text-color, var(--rw-public-color-muted));
    font-family: var(--rw-public-language-font-family, var(--rw-public-font-body));
    font-size: var(--rw-public-language-font-size, var(--rw-public-font-size-nav));
    font-weight: var(--rw-public-language-font-weight, 700);
    letter-spacing: var(--rw-public-language-letter-spacing, 0.01em);
    line-height: var(--rw-public-language-line-height, 1.2);
    justify-content: flex-end;
    width: 100%;
}

.rw-public-language-menu--spacing-compact {
    --rw-public-language-gap: 0.2rem;
}

.rw-public-language-menu--spacing-spacious {
    --rw-public-language-gap: 0.65rem;
}

.rw-public-language-menu__heading,
.rw-public-language-menu__heading-device,
.rw-public-language-menu__list,
.rw-public-language-menu__summary,
.rw-public-language-menu__link {
    align-items: center;
    display: inline-flex;
}

.rw-public-language-menu__heading-device,
.rw-public-language-menu__summary-icon {
    display: none;
}

.rw-public-language-menu__summary-icon svg {
    display: block;
    width: 1.15em;
    height: 1.15em;
    fill: currentColor;
}

.rw-public-language-menu__check {
    flex: 0 0 auto;
    color: var(--rw-public-language-active-text-color, var(--rw-public-color-primary));
    font-size: 1em;
    line-height: 1;
}

.rw-public-language-menu__list {
    display: flex;
    flex-wrap: wrap;
    gap: var(--rw-public-language-gap);
}

.rw-public-language-menu__dropdown {
    position: relative;
}

.rw-public-language-menu__summary,
.rw-public-language-menu__link {
    background: var(--rw-public-language-background-color, transparent);
    border: 1px solid transparent;
    border-radius: 999px;
    color: var(--rw-public-language-text-color, var(--rw-public-color-muted));
    cursor: pointer;
    gap: 0.45rem;
    min-height: 2.25rem;
    padding-inline: 0.75rem;
    text-decoration: none;
}

.rw-public-language-menu__summary {
    font-weight: 850;
}

.rw-public-language-menu__summary::-webkit-details-marker {
    display: none;
}

.rw-public-language-menu__link:hover,
.rw-public-language-menu__link:focus-visible,
.rw-public-language-menu__summary:hover,
.rw-public-language-menu__summary:focus-visible {
    background: var(--rw-public-language-hover-background-color, var(--rw-public-color-surface-muted));
    color: var(--rw-public-language-hover-text-color, var(--rw-public-color-text));
    outline: 0;
}

.rw-public-language-menu__link.is-active,
.rw-public-language-menu__link[aria-current='page'] {
    background: var(--rw-public-language-active-background-color, var(--rw-public-language-background-color, transparent));
    color: var(--rw-public-language-active-text-color, var(--rw-public-language-text-color, var(--rw-public-color-muted)));
    font-weight: 850;
}

.rw-public-language-menu__dropdown-list {
    background: var(--rw-public-color-surface);
    border: 1px solid var(--rw-public-color-border);
    border-radius: var(--rw-public-radius-md);
    box-shadow: var(--rw-public-shadow-card);
    display: grid;
    gap: 0.25rem;
    margin-top: 0.4rem;
    min-width: 10rem;
    padding: 0.45rem;
    position: absolute;
    right: 0;
    z-index: 70;
}

.rw-public-language-menu__flag {
    display: block;
    flex: 0 0 auto;
    height: 1rem;
    object-fit: cover;
    width: 1.45rem;
}

.rw-public-language-menu--flag-shape-rounded .rw-public-language-menu__flag {
    border-radius: 0.2rem;
}

.rw-public-language-menu--flag-shape-circle .rw-public-language-menu__flag {
    border-radius: 999px;
    height: 1.35rem;
    width: 1.35rem;
}

.rw-public-language-menu__label--visually-hidden {
    clip: rect(0, 0, 0, 0);
    border: 0;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    padding: 0;
    position: absolute;
    white-space: nowrap;
    width: 1px;
}

.rw-public-language-menu--mobile-dropdown .rw-public-language-menu__list,
.rw-public-language-menu--mobile-horizontal .rw-public-language-menu__dropdown {
    display: none;
}

.rw-public-language-menu--mobile-dropdown .rw-public-language-menu__dropdown {
    display: block;
}

.rw-public-language-menu--mobile-horizontal .rw-public-language-menu__list {
    display: flex;
}

.rw-public-language-menu--mobile-summary-icon .rw-public-language-menu__summary-icon--mobile,
.rw-public-language-menu__heading-device--mobile {
    display: inline-flex;
}

.rw-public-language-menu--mobile-align-left {
    justify-content: flex-start;
}

.rw-public-language-menu--mobile-align-center {
    justify-content: center;
}

.rw-public-language-menu--mobile-align-right {
    justify-content: flex-end;
}

@media (min-width: 760px) and (max-width: 1023.98px) {
    .rw-public-language-menu--tablet-dropdown .rw-public-language-menu__list,
    .rw-public-language-menu--tablet-horizontal .rw-public-language-menu__dropdown {
        display: none;
    }

    .rw-public-language-menu--tablet-dropdown .rw-public-language-menu__dropdown {
        display: block;
    }

    .rw-public-language-menu--tablet-horizontal .rw-public-language-menu__list {
        display: flex;
    }

    .rw-public-language-menu__heading-device,
    .rw-public-language-menu__summary-icon {
        display: none;
    }

    .rw-public-language-menu .rw-public-language-menu__heading-device--mobile,
    .rw-public-language-menu .rw-public-language-menu__heading-device--desktop,
    .rw-public-language-menu .rw-public-language-menu__summary-icon--mobile,
    .rw-public-language-menu .rw-public-language-menu__summary-icon--desktop {
        display: none;
    }

    .rw-public-language-menu--tablet-summary-icon .rw-public-language-menu__summary-icon--tablet,
    .rw-public-language-menu__heading-device--tablet {
        display: inline-flex;
    }

    .rw-public-language-menu--tablet-align-left {
        justify-content: flex-start;
    }

    .rw-public-language-menu--tablet-align-center {
        justify-content: center;
    }

    .rw-public-language-menu--tablet-align-right {
        justify-content: flex-end;
    }
}

@media (min-width: 1024px) {
    .rw-public-language-menu--desktop-dropdown .rw-public-language-menu__list,
    .rw-public-language-menu--desktop-horizontal .rw-public-language-menu__dropdown {
        display: none;
    }

    .rw-public-language-menu--desktop-dropdown .rw-public-language-menu__dropdown {
        display: block;
    }

    .rw-public-language-menu--desktop-horizontal .rw-public-language-menu__list {
        display: flex;
    }

    .rw-public-language-menu__heading-device,
    .rw-public-language-menu__summary-icon {
        display: none;
    }

    .rw-public-language-menu .rw-public-language-menu__heading-device--mobile,
    .rw-public-language-menu .rw-public-language-menu__heading-device--tablet,
    .rw-public-language-menu .rw-public-language-menu__summary-icon--mobile,
    .rw-public-language-menu .rw-public-language-menu__summary-icon--tablet {
        display: none;
    }

    .rw-public-language-menu--desktop-summary-icon .rw-public-language-menu__summary-icon--desktop,
    .rw-public-language-menu__heading-device--desktop {
        display: inline-flex;
    }

    .rw-public-language-menu--desktop-align-left {
        justify-content: flex-start;
    }

    .rw-public-language-menu--desktop-align-center {
        justify-content: center;
    }

    .rw-public-language-menu--desktop-align-right {
        justify-content: flex-end;
    }
}
CSS,

    'docs_navigation' => <<<'CSS'
.rw-docs-sidebar,
.rw-docs-toc {
    --rw-docs-nav-active-color: var(--rw-public-color-primary);
    color: inherit;
    font-size: 0.875rem;
}

.rw-public-content-block--docs-navigation,
.rw-public-content-block--docs-page-toc {
    position: sticky;
    top: 1rem;
    max-height: calc(100vh - 2rem);
    overflow: auto;
    scrollbar-width: thin;
}

.rw-docs-nav-list {
    display: grid;
    gap: 0.15rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.rw-docs-nav-list ul {
    display: grid;
    gap: 0.1rem;
    margin: 0.2rem 0 0.25rem 0.85rem;
    padding: 0;
    list-style: none;
}

.rw-docs-nav-link,
.rw-docs-toc-link {
    display: block;
    border-radius: 0.45rem;
    color: inherit;
    line-height: 1.35;
    text-decoration: none;
    transition:
        background-color 150ms ease,
        color 150ms ease;
}

.rw-docs-nav-link {
    padding: 0.35rem 0.55rem;
}

.rw-docs-toc-link {
    padding: 0.25rem 0.45rem;
    font-size: 0.8125rem;
}

.rw-docs-nav-link:hover,
.rw-docs-toc-link:hover,
.rw-docs-nav-link[aria-current='page'] {
    background: color-mix(in srgb, var(--rw-public-color-primary) 10%, transparent);
    color: var(--rw-docs-nav-active-color, var(--rw-public-color-primary));
}

.rw-docs-nav-link[aria-current='page'] {
    font-weight: 750;
}
CSS,

    'docs_content' => <<<'CSS'
.rw-docs-content {
    position: relative;
    min-width: 0;
}

.rw-docs-content.rw-public-prose {
    font-size: 1rem;
    line-height: 1.78;
}

.rw-docs-content h1 {
    margin-bottom: clamp(1.5rem, 4vw, 2.75rem);
    color: var(--rw-public-color-text);
    font-size: clamp(2.25rem, 5vw, 4rem);
    font-weight: 800;
    letter-spacing: -0.06em;
    line-height: 1;
}

.rw-docs-content h2 {
    scroll-margin-top: 6rem;
    margin-top: 2.75rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--rw-public-color-border);
    font-size: clamp(1.45rem, 3vw, 2rem);
    letter-spacing: -0.035em;
}

.rw-docs-content h3,
.rw-docs-content h4 {
    scroll-margin-top: 6rem;
}

.rw-docs-content p,
.rw-docs-content li {
    color: color-mix(in srgb, var(--rw-public-color-text) 78%, transparent);
}

.rw-docs-content a:not(.rw-public-button) {
    color: var(--rw-public-color-primary);
    font-weight: 650;
    text-decoration: none;
}

.rw-docs-content a:not(.rw-public-button):hover {
    text-decoration: underline;
    text-underline-offset: 0.18em;
}

.rw-docs-content pre {
    border: 1px solid color-mix(in srgb, var(--rw-public-color-border) 75%, transparent);
    border-radius: 0.85rem;
    background: color-mix(in srgb, var(--rw-public-color-text) 5%, transparent);
}
CSS,

    'docs_page_toc' => <<<'CSS'
.rw-docs-toc__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.rw-docs-toc__header h2 {
    margin: 0;
}

.rw-docs-toc__toggle,
.rw-docs-toc-restore {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border: 1px solid var(--rw-public-color-border);
    border-radius: 999px;
    background: var(--rw-public-color-surface, #fff);
    box-shadow: 0 10px 28px rgb(15 23 42 / 10%);
    color: var(--rw-public-color-text);
    cursor: pointer;
}
CSS,

    'docs_mobile_actions' => <<<'CSS'
.rw-public-content-block--docs-mobile-actions,
.rw-docs-mobile-actions {
    display: none;
}

@media (max-width: 1023px) {
    .rw-public-content-block--docs-mobile-actions {
        position: sticky;
        top: calc(var(--rw-docs-drawer-offset-top, 0px) + 0.75rem);
        z-index: 80;
        display: block;
        margin-bottom: 1rem;
        pointer-events: none;
    }

    .rw-docs-mobile-actions {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        pointer-events: auto;
    }
}
CSS,

    'site_user_account_controls' => <<<'CSS'
.rw-public-account-controls {
    align-items: center;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
}

.rw-public-account-controls__link,
.rw-public-account-controls__logout,
.rw-public-account-controls__summary {
    align-items: center;
    display: inline-flex;
}

.rw-public-account-controls__dropdown {
    position: relative;
}

.rw-public-account-controls__link,
.rw-public-account-controls__summary,
.rw-public-account-controls__button {
    background: transparent;
    cursor: pointer;
    min-height: 2.25rem;
    gap: 0.45rem;
    padding-inline: 0.75rem;
    border: 1px solid transparent;
    border-radius: 999px;
    color: var(--rw-public-color-muted);
    font-size: var(--rw-public-font-size-nav);
    font-weight: 700;
    line-height: 1.2;
    text-decoration: none;
}

.rw-public-account-controls__dropdown-list {
    background: var(--rw-public-color-surface);
    border: 1px solid var(--rw-public-color-border);
    border-radius: var(--rw-public-radius-md);
    box-shadow: var(--rw-public-shadow-card);
    display: grid;
    gap: 0.25rem;
    margin-top: 0.4rem;
    min-width: 11rem;
    padding: 0.45rem;
    position: absolute;
    right: 0;
    z-index: 70;
}

.rw-public-account-controls__label--visually-hidden {
    clip: rect(0, 0, 0, 0);
    border: 0;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    padding: 0;
    position: absolute;
    white-space: nowrap;
    width: 1px;
}
CSS,
];
