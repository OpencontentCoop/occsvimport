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
                                            <p><a href={'publish_to_web.png'|ezimage()}>Il documento Google Spreadsheet deve essere pubblicato sul web</a></p>
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
