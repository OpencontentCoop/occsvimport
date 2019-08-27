{section show=$error} {* $error can be either bool=false or array *}
    <div class="message-warning">
        <h2>
            {$error.message}
        </h2>
    </div>
{/section}

{if $tag}
<form enctype="multipart/form-data" method="post" name="importform" action="{concat('/csvimport/import_tag/', $tag.id)|ezurl(no)}">    
    <div class="border-box">
        <div class="border-tl"><div class="border-tr"><div class="border-tc"></div></div></div>
        <div class="border-ml"><div class="border-mr"><div class="border-mc float-break">

                    <div class="content-view-csvimport-export">

                        <div class="attribute-header">
                            <h1>Importa tag da Google Sheets in <a href={$tag.url|ezurl}>{$tag.keyword|wash}</a>
                            </h1>
                        </div>

                        <div class="attribute-description">

                            <div class="context-block">

                                <div class="box-bc">
                                    <div class="box-ml">
                                        <div class="box-content">                                            
                                            <div class="block">
                                                <input type="text" class="halfbox" name="GoogleSpreadsheetUrl" value="{$googleSpreadsheetUrl|wash()}"/>
                                                <input class="button" type="submit" name="SelectGoogleSpreadsheetButton" value="Selezione Google Sheet"/>
                                            </div>                                            
                                            {if $googleSpreadsheetUrl}
                                            <div class="block">                                                
                                                Seleziona foglio
                                                <select name="ImportGoogleSpreadsheetSheet" class="box">
                                                    {foreach $sheets as $sheet}
                                                        <option value="{$sheet|wash()}"{if $selected_sheet|eq($sheet)} selected="selected" {/if}>{$sheet|wash()}</option>
                                                    {/foreach}
                                                </select>
                                                <input class="button" type="submit" name="ImportGoogleSpreadsheetButton" value="Importa"/>                                                
                                            </div>
                                            {/if}

                                            <div class="block">
                                                <h4>Le intestazioni previste per il foglio di calcolo sono:</h4>
                                                <img style="max-width: 100%" src={'images/csv_sample.png'|ezdesign()} />
                                                <ul>
                                                    <li><code>tag</code> <strong>obbligatorio</strong>: il tag principale importato nella lingua di default</li>
                                                    <li><code>tag_LANGUAGE_CODE</code> (ad esempio <code>tag_eng-GB</code>): traduzione del tag</li>
                                                    <li><code>syn_*_LANGUAGE_CODE</code> (ad esempio <code>syn_1_ita-IT</code>, <code>syn_2_ita-IT</code>): sinonimo del tag (il carattere jolly * serve a permettere l'inserimento di più tag)</li>
                                                    <li><code>children</code>: titolo del foglio dello stesso documento Google Spreadsheet da cui importare i tag figli</li>
                                                </ul>
                                            </div>

                                            <div class="block">
                                                <h4>Note:</h4>
                                                <p><a href={'publish_to_web.png'|ezimage()}>Il documento Google Spreadsheet deve condiviso con chiunque abbia il link e pubblicato sul web</a></p>
                                                <p>L'importatore verifica l'esistenza del tag per evitare duplicazioni</p>
                                                <p>Non è possibile utilizzare un foglio children su più livelli dell'alberatura</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>


                            <input type="hidden" name="TagID" value="{$tag.id}"/>
                        </div>

                    </div>

        </div></div></div>
        <div class="border-bl"><div class="border-br"><div class="border-bc"></div></div></div>
    </div>
</form>    
{/if}
