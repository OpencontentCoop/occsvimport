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
<body class="bg-primary">

<div class="container my-5 bg-white rounded p-5 position-relative">
    <div id="loader" style="display:none; position: absolute;top: 0;right: 0;margin: 10px"><span class="glyphicon glyphicon-refresh gly-spin" aria-hidden="true"></span></div>
    <div class="row">
        <div class="col-12">
            <h1>Assistente migrazione<br /><small><code>{$context|wash()} - {$db_name|wash()}</code></small></h1>
            {if $migration_spreadsheet}
            {else}
                {if $context}
                    <p>Imposta il google spreadsheet per esportare i dati</p>
                {else}
                    <p>Imposta il google spreadsheet per importare i dati</p>
                {/if}
            {/if}

            <h2 class="my-4">Impostazioni spreadsheet</h2>
            <div class="alert alert-danger{if $error_spreadsheet|not} d-none{/if}">
                {if $error_spreadsheet}{$error_spreadsheet|wash()}{/if}
            </div>

            {if $migration_spreadsheet}
            <div class="row">
                <div class="col">
                    <p>
                        <a href="https://docs.google.com/spreadsheets/d/{$migration_spreadsheet}/edit" target="_blank">
                            {$migration_spreadsheet_title|wash()}<br />{$migration_spreadsheet}
                        </a>
                    </p>
                </div>
                <div class="col text-right">
                    <form action="{'/migration/dashboard'|ezurl(no)}" method="post">
                        <input type="hidden" name="remove_migration_spreadsheet" value="1" />
                        <input type="submit" class="btn btn-success" value="Rimuovi"/>
                        <input type="hidden" name="ezxform_token" value="{$ezxform_token}" />
                    </form>
                </div>
            </div>

            <div class="my-4 actions">

                    <td class="container options mb-4">
                        <table class="table">
                            <tr>
                                <td><a href="#" class="btn btn-sm btn-info" id="CheckAll">Inverti selezione</a></td>
                                <td></td>
                                <td></td>
                            </tr>
                        {foreach $class_hash as $class => $name}
                            <tr>
                                <td width="1" style="vertical-align: middle;white-space:nowrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" checked="checked" value="{$class}" name="Only" id="{$class}">
                                        <label class="form-check-label text-nowrap" for="{$class}">
                                            {$name|wash()}
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <div class="col result" id="result_{$class}"></div>
                                </td>
                                <td width="1">
                                    <p><a href="#" title="Ripristina formattazioni condizionali" class="btn btn-primary btn-sm" data-configuration="format" data-configure="{$class}"><span class="glyphicon glyphicon-adjust"></span></a></p>
                                    <p><a href="#" title="Ripristina validazione date" class="btn btn-primary btn-sm" data-configuration="date-validation" data-configure="{$class}"><span class="glyphicon glyphicon-resize-small"></span></a></p>
                                    <p><a href="#" title="Ripristina validazione vocabolari e relazioni" class="btn btn-primary btn-sm" data-configuration="range-validation" data-configure="{$class}"><span class="glyphicon glyphicon-link"></span></a></p>
                                </td>
                            </tr>
                        {/foreach}
                        </table>
                    </div>

                    <div class="options mb-4">

                        <div class="bg-light p-2 rounded border mx-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" checked="checked" value="update" name="isUpdate" id="isUpdate">
                                <label class="form-check-label" for="isUpdate">
                                    <b>Non sovrascrivere i dati già elaborati</b>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                    {if $context}
                        <a href="#" class="btn btn-primary btn-lg" data-action="export"><span class="loading d-none"><span class="glyphicon glyphicon-refresh gly-spin" aria-hidden="true"></span></span> <span class="glyphicon glyphicon-download"></span> Esporta dati</a>
                        <a href="#" class="btn btn-primary btn-lg" data-action="push"><span class="loading d-none"><span class="glyphicon glyphicon-refresh gly-spin" aria-hidden="true"></span></span> <span class="glyphicon glyphicon-upload"></span> Scrivi spreadsheet</a>
                    {else}
                        <a href="#" class="btn btn-primary btn-lg" data-action="pull"><span class="loading d-none"><span class="glyphicon glyphicon-refresh gly-spin" aria-hidden="true"></span></span> Leggi spreadsheet</a>
                        <a href="#" class="btn btn-primary btn-lg" data-action="import"><span class="loading d-none"><span class="glyphicon glyphicon-refresh gly-spin" aria-hidden="true"></span></span> Importa dati</a>
                    {/if}
                    </div>
                </div>
            {else}
                <form action="{'/migration/dashboard'|ezurl(no)}" method="post">
                    <div class="form-group">
                        <label for="migration_spreadsheet">Inserisci l'url del google spreadsheet</label>
                        <p class="text-muted">Lo spreadsheet deve essere condiviso con l'utente <code style="color:#000">{$google_user}</code> in modalità Editor</p>
                        <input type="text" id="migration_spreadsheet" class="form-control" name="migration_spreadsheet" />
                    </div>
                    <input type="submit" class="btn btn-success" value="Salva"/>
                    <input type="hidden" name="ezxform_token" value="{$ezxform_token}" />
                </form>
            {/if}
        </div>
    </div>
</div>

<div class="container-fluid my-5 bg-white">
    <div class="row">
        {if $migration_spreadsheet}
            <div class="col-12">
                <h2 class="my-4">Anteprima dati da {if $context}esportare{else}importare{/if}</h2>
            </div>
            <div class="col-12">
                <ul class="nav nav-tabs">
                    {foreach $class_hash as $class => $name}
                        <li class="nav-item"><a href="#" class="nav-link" data-identifier="{$class}">{$name|wash()}</a></li>
                    {/foreach}
                </ul>
            </div>
            <div class="col-12">
                <div>
                    <table id="data" class="table table-bordered table-sm display responsive no-wrap w-100" cellpadding="0" cellspacing="0"></table>
                </div>
            </div>
        {/if}
    </div>
</div>

<script type="text/javascript">
    var BaseUrl = "{'/migration/dashboard'|ezurl(no)}";
</script>
{literal}
    <script type="text/javascript">
      $(document).ready(function () {

        $('#CheckAll').on('click', function (e) {
          $('[name="Only"]').each(function (){
            $(this).trigger('click');
          });
          e.preventDefault();
        });

        var buildTable = function () {
          var data = $('#data');
          if ($.fn.dataTable.isDataTable('#data')) {
            data.DataTable().destroy();
          }
          var type = $('.nav-link.active').data('identifier');
          data.html('');
          $.getJSON(BaseUrl+'?fields='+type, function (columns) {
            data.DataTable({
              dom: 'it', //@todo pr
              pageLength: 100,
              responsive: true,
              columns: columns,
              ajax: {
                url: BaseUrl+'?datatable='+type,
                type: 'POST'
              },
              processing: true,
              serverSide: true
            });
          })
        };

        $('.nav-link').on('click', function (e) {
          e.preventDefault();
          $('.nav-link').removeClass('active');
          $(this).addClass('active');
          buildTable();
        })

        var parseStatus = function (data, cb, context) {
          if (data.status === 'error') {
            $('.alert-danger')
              .removeClass('d-none')
              .html(data.message);
            resetActions();
          } else if (data.status === 'unknown') {
            resetActions();
          } else if (data.status === 'running') {
            setActionActive(data.action)
            setTimeout(function () {
              checkStatus(cb, context);
            }, 2000)
          } else if (data.status === 'done') {
            resetActions();
          }

          if (data.options) {
            console.log(data.options)
            $.each(data.options, function (index, value) {
              if (index === 'class_filter') {
                $('.actions input[name="Only"]').each(function () {
                  $(this).attr('checked', false);
                  $(this).prop('checked', false);
                })
                $.each(value, function () {
                  $('#' + this).attr('checked', 'checked')
                    .prop('checked', 'checked');
                })
              } else if (index === 'update') {
                $('#isUpdate').attr('checked', value ? 'checked' : false)
                  .prop('checked', value ? 'checked' : false)
              }
            })
          }

          if (typeof data.message === 'object' && data.message){
            $.each(data.message, function (i, v){
              var updateStyle = 'warning';
              if (v.status === 'success') updateStyle = 'success';
              //if (v.status === 'error') updateStyle = 'danger';

              var updateMessage = v.update ?? '';
              if (updateMessage.length > 0){
                updateMessage = '<div class="alert alert-'+updateStyle+' p-1 my-1">' + updateMessage + '</div>';
              }
              var errorMessage = '';
              if (typeof v.message === 'string'){
                errorMessage = '<div class="alert alert-danger p-1 my-1">'+v.message+'</div>';
              }
              var statusMessage = '<span class="badge badge-'+updateStyle+'">' + v.status + '</span> ';
              var action = v.action || data.action;
              var actionMessage = '<span class="badge badge-primary">' + action + '</span> ';
              var dateMessage = '<code>'+moment(data.timestamp).format('DD/MM/YYYY HH:mm') + '</code> ';
              $('#result_'+i).html(statusMessage + actionMessage + dateMessage + updateMessage +  errorMessage)
            })
          }else{
            $.each(data.options.class_filter, function (){
              $('#result_'+this).html('<span class="badge badge-primary">' + data.action + '</span> '+moment(data.timestamp).format('DD/MM/YYYY HH:mm'))
            })
          }
        }

        var loader = $('#loader');
        var checkStatus = function (cb, context) {
          loader.show();
          $.getJSON(BaseUrl+'?status', function (data) {
            console.log(data);
            parseStatus(data);
            if ($.isFunction(cb)) {
              cb.call(context, data);
            }
            loader.hide();
          })
        };

        var setActionActive = function (action) {
          $('[data-action]').each(function () {
            $(this).attr('disabled', 'disabled');
            $(this).find('.loading').addClass('d-none');
          })
          $('[data-action="' + action + '"]')
            .addClass('active')
            .find('.loading').removeClass('d-none');
        };

        var resetActions = function () {
          $('[data-action]').removeAttr('disabled')
            .find('.loading').addClass('d-none');
        };

        $('[data-action]').on('click', function (e) {
          e.preventDefault();
          var self = $(this);
          var action = self.data('action');
          if (self.attr('disabled') !== 'disabled') {
            setActionActive(action);
            var classes = [];
            $('.actions input[name="Only"]').each(function () {
              if ($(this).is(':checked')) {
                classes.push($(this).val())
              }
            })
            var isUpdate = $('#isUpdate');
            $.getJSON(BaseUrl, {
              action: action,
              options: {
                class_filter: classes,
                update: isUpdate.is(':checked') ? isUpdate.val() : ''
              }
            }, function (data) {
              console.log('start', action);
              parseStatus(data);
            });
          }
        });

        $('[data-configure]').on('click', function (e) {
          e.preventDefault();
          loader.show();
          var self = $(this);
          var className = self.data('configure');
          var configuration = self.data('configuration');
          $.getJSON(BaseUrl, {
            configure: className,
            configuration: configuration
          }, function (data) {
            console.log(data);
            loader.hide();
          });
        });

        checkStatus(function () {
          $('.actions').removeClass('d-none');
        });
      });
    </script>
    <style type="text/css">
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
{/literal}

{* This comment will be replaced with actual debug report (if debug is on). *}
<!--DEBUG_REPORT-->
</body>
</html>
