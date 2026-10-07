{if $image}
    <img src="{$image.url|escape:'html':'UTF-8'}"
        {if $srcset} srcset="{$srcset|escape:'html':'UTF-8'}" sizes="{$sizes|escape:'html':'UTF-8'}"{/if}
        alt="{$alt|escape:'html':'UTF-8'}"
        {if $class} class="{$class|escape:'html':'UTF-8'}"{/if}
        {if $image.width && $image.height} width="{$image.width}" height="{$image.height}"{/if}
        loading="{$loading}"
        {if $fetchpriority} fetchpriority="{$fetchpriority}"{/if} />
{/if}
