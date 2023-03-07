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
            <p class="mb-5"><code>{if $context}{$context|wash()}@{/if}{$version|wash} - instance@{$instance|wash()} - db@{$db_name|wash()}<br />{$google_user}</code></p>

            <div class="alert alert-danger{if $error_spreadsheet|not} d-none{/if}">
                {if $error_spreadsheet}{$error_spreadsheet|wash()}{/if}
            </div>

            <form action="{'/migration/dashboard/credentials'|ezurl(no)}" method="post">
                <div class="form-group">
                    <h3>Impostazioni del service account</h3>
                    <p class="lead">
                        Accedi alla <a href="https://console.cloud.google.com/iam-admin/serviceaccounts">console di Google</a> per creare un service account dedicato
                    </p>
                    <label for="store_google_credentials" class="d-none">Inserisci il json</label>
                    <textarea id="store_google_credentials" class="form-control" name="store_google_credentials"></textarea>
                </div>
                <input type="submit" class="btn btn-success btn-lg" value="Salva"/>
                <input type="hidden" name="ezxform_token" value="{$ezxform_token}" />
            </form>

        </div>
    </div>
</div>

{* This comment will be replaced with actual debug report (if debug is on). *}
<!--DEBUG_REPORT-->
</body>
</html>
