<form enctype="multipart/form-data" method="post" name="configureform" action={concat("/csvimport/configure/", $node_id, "/", $import_identifier)|ezurl}>
    {section show=$error} {* $error can be either bool=false or array *}
        {section show=$error.number|ne(0)}
            <div class="message-warning">
                <h2>
                    <span class="time">[{currentdate()|l10n( shortdatetime )}]</span>
                    {$error.number}) {$error.message}
                </h2>
            </div>
        {/section}
    {/section}

    <div class="border-box">
        <div class="border-tl"><div class="border-tr"><div class="border-tc"></div></div></div>
        <div class="border-ml"><div class="border-mr"><div class="border-mc float-break">

            <div class="content-view-csvimport-export">

                <div class="attribute-header">
                    <h1>Configura importazione</h1>
                </div>

                <div class="attribute-description">
                    <div class="block">

                        <label>
                            Imposta {$feed_title|wash()} in <a href="{$node.url_alias|ezurl(no)}"  title="Importa in {$node.name|wash()}">{$node.name|wash()}</a>
                        </label>
                    </div>

                    <div class="block">
                        <label>
                            Seleziona foglio
                            <select name="ImportGoogleSpreadsheetSheet" class="box">
                                {foreach $sheets as $sheet}
                                    <option value="{$sheet|wash()}"{if $selected_sheet|eq($sheet)} selected="selected" {/if}>{$sheet|wash()}</option>
                                {/foreach}
                            </select>
                        </label>
                    </div>

                    <div class="block">
                        <label>
                            Identificare di classe
                            {def $classlist = fetch( 'class', 'list', hash( 'class_filter', ezini( 'ListSettings', 'IncludeClasses', 'lists.ini' ),'sort_by', array( 'name', true() ) ) )}
                            <select name="ImportGoogleSpreadsheetClass" class="box">
                            {foreach $classlist as $class}
                                <option value="{$class.identifier}"{if $selected_class_identifier|eq($class.identifier)} selected="selected" {/if}>{$class.name|wash()}</option>
                            {/foreach}
                            </select>
                        </label>
                    </div>

                    <div class="block">
                        <label>
                            <input type="checkbox" name="Incremental" value="1" {if $incremental} checked="checked" {/if}/>Import di tipo incrementale?
                        </label>
                    </div>

                    {if is_set($class_attributes)}
                    <table class="list" cellspacing="0">
                        <tr>
                            <th colspan="2">Attributo</th>
                            <th>Intestazione foglio</th>
                        </tr>
                    {foreach $class_attributes as $class_attribute sequence array( 'bglight', 'bgdark' ) as $sequence}
                    <tr class="{$sequence}">
                        <td>
                            {$class_attribute.name|wash()}
                        </td>
                        <td>
                            {$class_attribute.identifier|wash()} ({$class_attribute.data_type_string|wash()})
                        </td>
                        <td>
                            <select name="MapFields[{$class_attribute.identifier}]">
                                <option></option>
                                {foreach $headers as $header}
                                <option value="{$header}"{if or(
                                    and(is_set($mapped_headers[$class_attribute.identifier]), $mapped_headers[$class_attribute.identifier])|eq($header),
                                    $header|eq($class_attribute.identifier)
                                )} selected="selected" {/if}>{$header|wash()}</option>
                                {/foreach}
                            </select>
                        </td>
                    </tr>
                    {/foreach}
                    </table>
                    {/if}

                    <div class="block">
                        <label>
                            Cartella file
                            <input type="text" name="FileDir" value="{$file_dir|wash()}" class="halfbox" />
                        </label>
                    </div>

                    <div class="block">
                        <label>
                            Formato data
                            <input type="text" name="DateFormat" value="{$date_format|wash()}" class="halfbox" />
                        </label>
                    </div>

                    <input class="button" type="submit" name="UpdateGoogleSpreadsheetButton" value="Update configurator"/>

                    <input class="button" type="submit" name="ImportGoogleSpreadsheetButton" value="Import"/>

                </div>

            </div>

                </div></div></div>
        <div class="border-bl"><div class="border-br"><div class="border-bc"></div></div></div>
    </div>
</form>
