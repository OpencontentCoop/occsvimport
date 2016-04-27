<form enctype="multipart/form-data" method="post" name="importform" action={"/csvimport/import"|ezurl}>
{section show=$error} {* $error can be either bool=false or array *}
    {section show=$error.number|ne(0)}
       <div class="message-warning"><h2><span class="time">[{currentdate()|l10n( shortdatetime )}]</span>{$error.number}) {$error.message} </h2></div>
    {/section}
{/section}

<div class="border-box">
<div class="border-tl"><div class="border-tr"><div class="border-tc"></div></div></div>
<div class="border-ml"><div class="border-mr"><div class="border-mc float-break">

<div class="content-view-csvimport-export">
    
    <div class="attribute-header">
        <h1>Importa oggetti da file CSV in <a href={$node.url_alias|ezurl()} title="Importa in {$node.name|wash()}">{$node.name|wash()}</a></h1>
    </div>

    <div class="attribute-description">
        <div class="block">
            <input type="checkbox" name="Incremental" value="1" />Import di tipo incrementale?
        </div>

    	<div class="block">
    		<input type="file" name="ImportFile" />
            <input class="button" type="submit" name="UploadFileButton" value="Upload file" />
        </div>


    	
    	<input type="hidden" name="ObjectID" value="{$ObjectID}" />
    	<input type="hidden" name="NodeID" value="{$NodeID}" />
    </div>

</div>

</div></div></div>
<div class="border-bl"><div class="border-br"><div class="border-bc"></div></div></div>
</div>

</form>
