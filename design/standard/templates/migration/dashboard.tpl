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
<body class="bg-{if $context|not}success{else}primary{/if}">

<div id="loader" style="display:none; position: fixed;top: 0;right: 0;margin: 10px"><span class="glyphicon glyphicon-refresh gly-spin" aria-hidden="true"></span></div>
<div class="container my-5 bg-white rounded p-5 position-relative">
    <div class="row">
        <div class="col-12">
            <h1>Assistente migrazione <span class="badge badge-{if $context|not}success{else}primary{/if}">{if $context} esportazione {else} importazione {/if} dati</span></h1>
            <p class="mb-5"><code>{if $context}{$context|wash()}@{/if}{$version|wash} - instance@{$instance|wash()} - db@{$db_name|wash()}<br />{$google_user}</code></p>
            {if $migration_spreadsheet}
                <h4 class="my-4">Impostazioni dello spreadsheet {if $context} di destinazione {else} sorgente {/if}</h4>
            {else}
                {if $context}
                    <h2>Imposta il google spreadsheet per esportare i dati</h2>
                {else}
                    <h2>Imposta il google spreadsheet per importare i dati</h2>
                {/if}
            {/if}

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

                    <div class="container options mb-4">
                        <table class="table">
                            <tr>
                                <th colspan="2"><a href="#" class="btn btn-sm btn-link" id="CheckAll" title="Inverti selezione"><span class="glyphicon glyphicon-check"></span> Inverti selezione</a></th>
                            </tr>
                        {foreach $class_hash as $class => $name}
                            <tr>
                                <td width="1" style="white-space:nowrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" checked="checked" value="{$class}" name="Only" id="{$class}">
                                        <label class="form-check-label text-nowrap h5" style="cursor:pointer" for="{$class}">
                                            {$name|wash()}
                                        </label>
                                    </div>

                                    <p class="mt-5">
                                        <a href="#" title="Ripristina formattazioni condizionali" class="btn btn-outline-primary btn-sm" data-configuration="format" data-configure="{$class}"><span class="glyphicon glyphicon-adjust"></span></a>
                                        <a href="#" title="Ripristina validazione date" class="btn btn-outline-primary btn-sm" data-configuration="date-validation" data-configure="{$class}"><span class="glyphicon glyphicon-calendar"></span></a>
                                        <a href="#" title="Ripristina validazione vocabolari e relazioni" class="btn btn-outline-primary btn-sm" data-configuration="range-validation" data-configure="{$class}"><span class="glyphicon glyphicon-link"></span></a>
                                    </p>
                                </td>
                                <td>
                                    <div class="col result" id="result_{$class}"></div>
                                </td>
                            </tr>
                        {/foreach}
                        </table>
                    </div>

                    <div class="options mb-4">
                        <div class="bg-light p-2 rounded border mx-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" {if $context}checked="checked"{/if} value="update" name="isUpdate" id="isUpdate">
                                <label class="form-check-label h5" for="isUpdate" style="cursor:pointer">
                                    <b>{if $context}Non sovrascrivere i dati già elaborati{else}Aggiorna i contenuti già importati{/if}</b>
                                </label>
                            </div>
                            {if $context|not()}
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" value="update" name="doValidation" id="doValidation">
                                    <label class="form-check-label h5" for="doValidation" style="cursor:pointer">
                                        <b>Valida i dati quando leggi lo spreadsheet</b>
                                    </label>
                                </div>
                            {/if}
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
                        <ol class="lead">
                            {if $context}
                                <li>Crea un nuovo google spreadsheet copiandolo dal <a href="https://link.opencontent.it/new-kit-{$context|wash()}" target="_blank">modello</a></li>
                                <li>Condividilo con l'utente <code style="color:#000">{$google_user}</code> in modalità Editor</li>
                            {/if}
                            <li>Incolla l'url del tuo google spreadsheet{if $context|not()} condiviso con l'utente <code style="color:#000">{$google_user}</code> in modalità Editor{/if}</li>
                        </ol>
                        <label for="migration_spreadsheet" class="d-none">Inserisci qui l'url</label>
                        <input type="text" id="migration_spreadsheet" class="form-control" name="migration_spreadsheet" placeholder="Inserisci qui l'url del tuo google spreadsheet"/>
                    </div>
                    <input type="submit" class="btn btn-success btn-lg" value="Salva"/>
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
                <h2 class="my-4">{if $context}Anteprima dati da esportare{/if}</h2>
                {if $context|not()}
                    <select id="useContext" name="useContext" class="form-control form-control-lg" style="width: 300px;font-weight: bold">
                        <option value="0" selected="selected">Errori di importazione</option>
                        <option value="1">Dati letti dallo spreadsheet</option>
                    </select>
                {/if}
            </div>
            <div class="col-12">
                <ul class="nav nav-tabs">
                    {foreach $class_hash as $class => $name}
                        <li class="nav-item"><a href="#" class="nav-link" data-identifier="{$class}">{$name|wash()}</a></li>
                    {/foreach}
                </ul>
            </div>
            <div class="col-12">
                <div class="my-3">
                    <table id="data" class="table table-striped table-bordered table-sm display responsive no-wrap w-100" cellpadding="0" cellspacing="0"></table>
                </div>
            </div>
        {/if}
    </div>
</div>

<script type="text/javascript">
  console.log('Version {$version}');
  var BaseUrl = "{'/migration/dashboard'|ezurl(no)}";
  var Context = {cond($context, concat('"', $context, '"'), false)};
  console.log('Context '+Context);
</script>
{literal}
    <script type="text/javascript">
      $(document).ready(function () {

        $.fn.dataTable.ext.errMode = 'throw';

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

          var renderField = function ( data, type, row, meta ) {
            if (typeof data === 'object') {
              return '<small style="font-size:.7em; white-space:nowrap">Ultima modifica: ' + data.modified_at + '</small><br />' +
                '<small style="font-size:.7em; white-space:nowrap">Ultima esecuzione: ' + data.executed_at + '</small>';
            }
            if (typeof data === 'string' && data.startsWith('http')){
              var splitted = data.split('#');
              var name = splitted[1] ?? data;
              var extra = '';
              if (name !== data){
                var className = splitted[2] ?? '';
                var extraLink = '#';
                if (className.length > 0){
                  extraLink = '/migration/dashboard/'+className+'/'+name;
                  extra += ' <a target="_blank" class="badge badge-info m-1" href="'+extraLink+'">Data</a>';
                  extra += ' <a target="_blank" class="badge badge-info m-1" href="/openpa/object/'+row['__id']+'">Local content</a>';
                  extra += ' <a target="_blank" class="badge badge-info m-1" href="/opendata/api/content/read/'+row['__id']+'">Local api content</a>';
                }else{
                  extraLink = '/migration/dashboard/payload/'+name;
                  extra = ' <a target="_blank" class="badge badge-info m-1" href="'+extraLink+'">Payload</a>';
                }
              }
              return '<a target="_blank" href="'+splitted[0]+'" title="'+splitted[0]+'"><small>'+name+'</small></a><span class="d-block text-nowrap">'+extra+'</span>';
            }
            return data;
          }

          var useContextSelect = $('#useContext');
          var useContext = useContextSelect.length > 0 ? useContextSelect.val() : 1;
          if (useContextSelect){
            useContextSelect.on('change', function(){
              $('.nav-link.active').trigger('click');
            })
          }
          $.getJSON(BaseUrl+'/fields/'+type+'?useContext='+useContext, function (columns) {
            $.each(columns, function (){
              this.render = renderField;
            })
            data.DataTable({
              responsive: true,
              dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>" +
                "<'row'<'col-sm-12'tr>>",
              columns: columns,
              ajax: {
                url: BaseUrl+'/datatable/'+type+'?useContext='+useContext,
                type: 'POST',
                data: {
                  ezxform_token: $('[name="ezxform_token"]').attr('value')
                }
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
            if (data.message.startsWith('<!DOCTYPE')){
                $('body').replaceWith(data.message);
            }else {
              $('.alert-danger')
                .removeClass('d-none')
                .html(data.message);
            }
            resetActions();
          } else if (data.status === 'unknown') {
            resetActions();
          } else if (data.status === 'running' || data.status === 'pending') {
            setActionActive(data.action)
            setTimeout(function () {
              checkStatus(cb, context);
            }, 2000)
          } else if (data.status === 'done') {
            resetActions();
          }

          if (data.options) {
            // console.log(data.options)
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
              } else if (index === 'validate') {
                $('#doValidation').attr('checked', value ? 'checked' : false)
                  .prop('checked', value ? 'checked' : false)
              }
            })
          }

          if (typeof data.message === 'object' && data.message){
            $.each(data.message, function (i, v){
              var updateStyle = 'warning';
              if (v.status === 'success') updateStyle = 'success';
              if (v.status === 'pending') updateStyle = 'info';
              if (v.status === 'warning') updateStyle = 'danger';

              var updateMessage = v.update ?? '';
              if (updateMessage.length > 0){
                updateMessage = '<div class="alert alert-'+updateStyle+' p-1 my-1">' + updateMessage + '</div>';
              }
              var errorMessage = '';
              if (typeof v.message === 'string'){
                errorMessage = '<div class="alert alert-danger p-1 my-1">'+v.message+'</div>';
              }
              var statusIcon = '';
              if (v.status === 'warning'){
                statusIcon = '<span class="glyphicon glyphicon-warning-sign text-'+updateStyle+'"></span> ';
              }
              var statusMessage = statusIcon + '<span class="badge badge-'+updateStyle+'">' + v.status + '</span> ';
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
          $.getJSON(BaseUrl+'/status', function (data) {
            console.log(data.action, data.status, data);
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
            var doValidation = $('#doValidation');
            $.getJSON(BaseUrl+'/run', {
              action: action,
              options: {
                class_filter: classes,
                update: isUpdate.is(':checked') ? isUpdate.val() : '',
                validate: doValidation.is(':checked') ? doValidation.val() : '',
                import_url_alias: true
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
          $.getJSON(BaseUrl+'/configure/'+className, {
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
