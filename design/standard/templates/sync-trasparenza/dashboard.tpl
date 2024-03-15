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
    'jq.dt.min.js',
    'dt.b4.min.js',
    'dataTables.responsive.min.js'
    ))}
    {ezcss_load(array(
    'bootstrap.min.css',
    'glyphicon.css',
    'dt.b4.min.css',
    'responsive.dataTables.min.css'
    ))}
</head>
<body class="bg-success">

<div id="loader" style="display:none; position: fixed;top: 0;right: 0;margin: 10px"><span class="glyphicon glyphicon-refresh gly-spin" aria-hidden="true"></span></div>
<form action="{'/sync-trasparenza/dashboard'|ezurl(no)}" method="post">
<div class="container-fluid my-5 bg-white rounded p-5 position-relative">
    <div class="row">
        <div class="col-12">
            <h1>Sync trasparenza</h1>
            <p class="mb-5"><a href="/sync-trasparenza/dashboard/credentials"><code>{$google_user}</code></a></p>
            {if and($trasparenza_spreadsheet, $google_user)}
                <h4 class="my-4">Impostazioni dello spreadsheet</h4>
            {elseif $google_user}
                <h2>Imposta il google spreadsheet</h2>
            {else}
                <h2>Configura un account di servizio Google</h2>
            {/if}

            {if $google_user}
                <div class="alert alert-danger{if $error_spreadsheet|not} d-none{/if}">
                    {if $error_spreadsheet}{$error_spreadsheet|wash()}{/if}
                </div>
            {/if}

            {if and($trasparenza_spreadsheet, $google_user)}
                <div class="row">
                    <div class="col">
                        <p>
                            <a href="https://docs.google.com/spreadsheets/d/{$trasparenza_spreadsheet}/edit"
                               target="_blank">
                                {$trasparenza_spreadsheet_title|wash()}<br/>{$trasparenza_spinstancereadsheet}
                            </a>
                        </p>
                    </div>
                    <div class="col-7 text-right">
                        {if $with_check|gt(0)}
                            <span>{$with_check} pagina/e non sincronizzata/e</span>
                        {/if}
                        <input type="hidden" name="ezxform_token" value="{$ezxform_token}"/>
                        <input type="submit" class="btn btn-danger ml-3" name="remove_trasparenza_spreadsheet" value="Rimuovi sheet"/>
                        <input type="submit" class="btn btn-success ml-3" name="refresh_trasparenza_spreadsheet" value="Controlla dati"/>
                        <input type="submit" class="btn btn-primary ml-3" name="sync_trasparenza_spreadsheet" value="Sincronizza selezionati"/>
                    </div>
                </div>
                <div class="my-4 actions"></div>
            {else}
                {if $google_user}
                        <div class="form-group">
                            <ol class="lead">
                                <li>Procurati uno spreadsheet con un modello di alberatura</li>
                                <li>Condividilo con l'utente <code style="color:#000">{$google_user}</code> in modalità
                                    Editor
                                </li>
                                <li>Incolla l'url del tuo google spreadsheet</li>
                            </ol>
                            <label for="trasparenza_spreadsheet" class="d-none">Inserisci qui l'url</label>
                            <input type="text" id="trasparenza_spreadsheet" class="form-control"
                                   name="trasparenza_spreadsheet"
                                   placeholder="Inserisci qui l'url del tuo google spreadsheet"/>
                        </div>
                        <input type="submit" class="btn btn-success btn-lg" value="Salva"/>
                        <input type="hidden" name="ezxform_token" value="{$ezxform_token}"/>
                {else}
                    <p class="lead">
                        Accedi alla <a href="/sync-trasparenza/dashboard/credentials">pagina di gestione credenziali</a>
                    </p>
                {/if}
            {/if}
        </div>
    </div>
    {if is_set($data)}
    <table class="table table-sm">
        <thead>
            <tr>
                <th><a href="#" class="btn btn-sm btn-link" id="CheckAll" title="Inverti selezione"><span class="glyphicon glyphicon-check"></span></a></th>
                {foreach $fields as $identifier => $field}
                    <th {if $identifier|eq('titolo')}colspan="2"{/if} style="white-space:nowrap">{$field||shorten(10)}</th>
                {/foreach}
                <th></th>
            </tr>
        </thead>
        <tbody>
        {foreach $data as $item}
            <tr data-remote="{$item.remote_id}">
                <td>
                    <input id="Select-{$item.remote_id}" type="checkbox" {if count($item.check)}checked="checked"{/if} value="{$item.remote_id}" name="Select[]" />
                </td>
                {foreach $fields as $identifier => $field}
                    {if $identifier|eq('titolo')}
                        <td>
                            {if is_set($item.check.error)|not()}
                                <a href="{concat('openpa/object/',$item.remote_id)|ezurl(no)}"><span class="glyphicon glyphicon-link ml-2 text-success" aria-hidden="true"></span></a>
                            {/if}
                        </td>
                        <td>
                            <label style="white-space:nowrap" for="Select-{$item.remote_id}">{$item.tree|oc_shorten(70)}</label>
                            {if is_set($item.check.error)}
                                <small class="text-danger d-block">{$item.check.error|wash()}</small>
                            {/if}
                        </td>
                    {else}
                        <td data-field="{$identifier}" style="border-left: 1px dotted #bbb;text-align:center">
                            <span class="loading d-none glyphicon glyphicon-refresh gly-spin" aria-hidden="true"></span>
                            <span class="success d-none glyphicon glyphicon-check text-success" aria-hidden="true"></span>
                            {if or(is_set($item.check[$identifier]), is_set($item.check.error))}<span class="danger glyphicon glyphicon-warning-sign text-danger" aria-hidden="true"></span>{/if}
                        </td>
                    {/if}
                {/foreach}
                <td></td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    {/if}
</div>
</form>

<script type="text/javascript">
  var BaseUrl = "{'/sync-trasparenza/dashboard'|ezurl(no)}";
</script>
{literal}
<style>
    .gly-spin {
        -webkit-animation: spin 2s infinite linear;
        -moz-animation: spin 2s infinite linear;
        -o-animation: spin 2s infinite linear;
        animation: spin 2s infinite linear;
    }
    @-moz-keyframes spin {
        0% {
            -moz-transform: rotate(0deg);
        }
        100% {
            -moz-transform: rotate(359deg);
        }
    }
    @-webkit-keyframes spin {
        0% {
            -webkit-transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(359deg);
        }
    }
    @-o-keyframes spin {
        0% {
            -o-transform: rotate(0deg);
        }
        100% {
            -o-transform: rotate(359deg);
        }
    }
    @keyframes spin {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(359deg);
            transform: rotate(359deg);
        }
    }
</style>
<script type="text/javascript">
  $(document).ready(function () {
    $('#CheckAll').on('click', function (e) {
      $('[name="Select[]"]').each(function (){
        $(this).trigger('click');
      });
      e.preventDefault();
    });
  });
  {/literal}
</script>
{* This comment will be replaced with actual debug report (if debug is on). *}
<!--DEBUG_REPORT-->
</body>
</html>
