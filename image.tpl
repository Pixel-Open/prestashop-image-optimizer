{if image}
    {if $sources|count > 0}
        <picture>
            {foreach from=$sources item=source}
                <source media="(max-width: {$source.width}px)" srcset="{$urls.base_url}{$source.path}" />
            {/foreach}
            <img src="{$urls.base_url}{$image.path}" alt="{$alt}" class="{$class}" loading="lazy" />
        </picture>
    {else}
        <img src="{$urls.base_url}{$image.path}" alt="{$alt}" class="{$class}" width="{$image.width}" height="{$image.height}" loading="lazy" />
    {/if}
{/if}