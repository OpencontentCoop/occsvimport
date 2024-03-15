<!doctype html>
<html lang="it">
<head>
    <title>Sync trasparenza</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {ezscript_load(array(
    'ezjsc::jquery',
    'ezjsc::jqueryUI',
    'ezjsc::jqueryio',
    'moment-with-locales.min.js',
    'handlebars.min.js',
    'alpaca.min.js',
    'jquery.dataTables.js',
    'dataTables.responsive.min.js',
    'dataTables.bootstrap.js'
    ))}
    {ezcss_load(array(
    'bootstrap.min.css',
    'glyphicon.css',
    'dataTables.bootstrap.css',
    'responsive.dataTables.min.css'
    ))}
</head>
<body class="bg-success">

<a href="{'sync-trasparenza/dashboard'|ezurl(no)}" type="submit" class="btn btn-link go-back text-white">
    <span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span>
    {'Back'|i18n( 'design/ocbootstrap/content/history' )}
</a>
{if $raw}
    <a class="btn btn-link go-back text-white" href="{concat('sync-trasparenza/dashboard/diff/',$item.remote_id)|ezurl(no)}">Normal view</a>
{else}
    <a class="btn btn-link go-back text-white" href="{concat('sync-trasparenza/dashboard/diff/',$item.remote_id, '?raw')|ezurl(no)}">Source view</a>
{/if}
<div class="container my-5 p-5 position-relative">
    <div class="row mb-3">
        <div class="col text-center">
            <h3 class="text-center text-white text-uppercase">LOCALE</h3>
        </div>
        <div class="col text-center">
            <h3 class="text-center text-white text-uppercase">PROTOTIPO</h3>
        </div>
    </div>
    {foreach $diff.locale as $id => $value}
    <h5 class="text-center text-white text-uppercase">{$id}</h5>
    <div class="row mb-5">
        <div class="col{if $raw}-12{/if} rounded p-3 {if is_set($diff.check[$id])}alert-danger border-danger border-4{else}bg-white{/if}">
            {if $raw}
                <pre style="white-space: break-spaces">{$value|wash()}</pre>
            {else}
            <div>{$value}</div>
            {/if}
        </div>
        <div class="col-1{if $raw}2 py-2{/if} text-center">
            {if is_set($diff.check[$id])}
                <form method="post" action="{concat('sync-trasparenza/dashboard/diff/',$item.remote_id)|ezurl(no)}">
                    <input type="hidden" value="{$id|wash()}" name="Field">
                    <button class="btn btn-link btn-lg text-white" name="Sync">
                        <span class="glyphicon glyphicon-circle-arrow-{if $raw}up{else}left{/if}" style="font-size: 1.5em;" aria-hidden="true"></span>
                    </button>
                </form>
            {/if}
        </div>
        <div class="col{if $raw}-12{/if} bg-white rounded p-3">
            {if $raw}
                <pre style="white-space: break-spaces">{$diff.remote[$id]|wash()}</pre>
            {else}
                <div>{$diff.remote[$id]}</div>
            {/if}
        </div>
    </div>
    {/foreach}
</div>

{* This comment will be replaced with actual debug report (if debug is on). *}
<!--DEBUG_REPORT-->
</body>
</html>
