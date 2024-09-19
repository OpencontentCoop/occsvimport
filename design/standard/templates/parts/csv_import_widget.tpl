<form class="bg-light p-2 rounded" enctype="multipart/form-data" method="post" name="importform" action={"/csvimport/import"|ezurl}>
    <p class="font-sans-serif">
        Caricare un file in formato zip
        e cliccare su <code>{'Upload'|i18n( 'design/standard/content/upload' )}</code>
        <br>
        L'archivio zip deve contenere un file csv con nome <code>{$class_identifier}.csv</code> con carattere separatore <code class="border">;</code>
        {if $example}
            e che deve rispettare le intestazione di colonna presenti in <a href="{$example}">questo esempio</a>
        {/if}
    </p>
    <input type="hidden" name="NodeID" value="{$parent_node_id}"/>
    <input type="file" name="ImportFile" accept=".zip"/>
    <button class="btn btn-primary btn-xs" type="submit" name="UploadFileButton">{'Upload'|i18n( 'design/standard/content/upload' )}</button>
    <input type="hidden" name="RedirectUrl" value="{concat('/content/view/full/', $parent_node_id)}"/>
</form>
<div class="csvimport-history" data-parent="{$parent_node_id}" data-class="{$class_identifier}"></div>

{ezscript_require(array(
    'ezjsc::jquery',
    'moment-with-locales.min.js',
    'jsrender.js'
))}

{run-once}
{literal}
<script id="tpl-csvimport-history" type="text/x-jsrender">
{{if items.length}}
<div class="my-4">
    <a href="#" data-refresh class="pull-right float-end"><i class="fa fa-refresh"></i></a>
    <strong>Cronologia caricamenti massivi</strong>
    <table class="table table-compat table-sm">
        <thead>
        <tr>{/literal}
            <th style="white-space: nowrap;">{"Requested on"|i18n( 'extension/sqliimport' )}</th>
            <th>{"Status"|i18n( 'extension/sqliimport' )}</th>
            <th style="white-space: nowrap;">{"Duration"|i18n( 'extension/sqliimport' )}</th>
        {literal}</tr>
        </thead>
        <tbody>
        {{for items}}
        <tr>
            <td>{{:requested_time}}</td>
            <td>
                {{:status_string}}
            </td>
            <td>
            {{if status > 0}}
                {{:duration.hour}}h {{:duration.minute}}min {{:duration.second}}sec
            {{/if}}
            </td>
        </tr>
        {{/for}}
        </tbody>
    </table>
</div>
{{/if}}
</script>
<script>
    $(document).ready(function (){
      $('.csvimport-history').each(function (){
        let self = $(this)
        let endpoint = '/csvimport/history/'+self.data('parent')+'/'+self.data('class')
        let loadHistory = function (){
          $.get(endpoint, function (response){
            let history = $($.templates("#tpl-csvimport-history").render(response));
            history.find('[data-refresh]').on('click', function (e){
              loadHistory();
              e.preventDefault();
            })
            self.html(history)
          })
        }
        loadHistory();
      })
    })
</script>
{/literal}
{/run-once}