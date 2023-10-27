<!doctype html>
<html lang="it">
<head>
    <title>Assistente migrazione</title>
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
<body class="bg-{if $context|not}success{else}primary{/if}">

<div class="container my-5 bg-white rounded p-5 position-relative">
    <div class="row">
        <div class="col-12">
            <h1><a href="{'/migration/dashboard'|ezurl(no)}">Assistente migrazione</a></h1>
            <p class="mb-5"><code>{if $context}{$context|wash()}@{/if}{$version|wash} - instance@{$instance|wash()} - db@{$db_name|wash()}</code></p>

            <h3>Impostazioni del account di servizio Google</h3>
            {if $google_user}
                <div class="border rounded p-4 mb-5">
                    <dl class="dl-horizontal mt-0">
                        <dt>Account di servizio attivo</dt>
                        <dd><code>{$google_user}</code></dd>
                        <dt>Project ID</dt>
                        <dd><code>{$google_credentials.project_id}</code></dd>
                    </dl>
                    <form action="{'/migration/dashboard/credentials'|ezurl(no)}" method="post">
                        <input type="hidden" name="store_google_credentials" />
                        <input type="submit" class="btn btn-danger btn-lg" value="Rimuovi account di servizio"/>
                        <input type="hidden" name="ezxform_token" value="{$ezxform_token}" />
                    </form>
                </div>
            {else}
                <p class="lead mb-4">
                    Nessun account di servizio configurato
                </p>
            {/if}
            <h5>Per {if $google_user}modificare il{else}creare un nuovo{/if} account di servizio Google</h5>
            <ol class="lead">
                <li>Accedi alla <a href="https://console.cloud.google.com">console Google cloud</a></li>
                <li>Crea un nuovo progetto in Api e servizi</li>
                <li>Abilita le Google Sheets API per i progetto creato</li>
                <li>In Credenziali crea account di servizio</li>
                <li>Aggiungi una chiave in JSON per l'account di servizio</li>
                <li>Incolla qui il contenuto JSON e salva</li>
            </ol>
            <form action="{'/migration/dashboard/credentials'|ezurl(no)}" method="post">
                <label for="store_google_credentials" class="d-none">Inserisci il json</label>
                <textarea id="store_google_credentials" class="form-control mb-2" required name="store_google_credentials"></textarea>
                <input type="submit" class="btn btn-success btn-lg" value="Salva account di servizio"/>
                <input type="hidden" name="ezxform_token" value="{$ezxform_token}" />
            </form>

        </div>
    </div>
</div>

{* This comment will be replaced with actual debug report (if debug is on). *}
<!--DEBUG_REPORT-->
</body>
</html>
