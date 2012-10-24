<form enctype="multipart/form-data" method="post" name="exportform" action={"/csvimport/export"|ezurl}>

<div class="border-box">
<div class="border-tl"><div class="border-tr"><div class="border-tc"></div></div></div>
<div class="border-ml"><div class="border-mr"><div class="border-mc float-break">

<div class="content-view-csvimport-export">
    
    <div class="attribute-header">
        <h1>Esporta intestazioni CSV per oggetti di classe {$class_identifier}</h1>
    </div>
    
    <div class="message-feedback">
        <h2>Informazioni</h2>
        <ul>
            <li>Il file CSV deve avere come nome l'identificatore della classe</li>
            <li>Il file CSV deve avere come delimitatore di campo il carattere < {ezini( 'Settings', 'CSVDelimiter', 'csvimport.ini' )} > </li>
            <li>I campi del file CSV sono racchiusi tra caratteri < {ezini( 'Settings', 'CSVEnclosure', 'csvimport.ini' )} ></li>
            <li>Per il formato degli attributi si veda <a target="_blank" href="https://github.com/lolautruche/SQLIImport/blob/master/doc/fromString.txt">SQLIImport - fromString() Appendix</a><br />
            <strong>Attenzione fanno eccezione:</strong>:
                <ul>
                <li>i campi relazione a oggetti (ezobjectrelation) e relazioni a oggetti (ezobjectrelationlist) in cui i valori considerati sono il titolo dell'oggetto e devono essere separati da virgola. Il sistema importa gli oggetti di cui trova corrispondenza nell'albero contenuti: esempio per Tipo di strutture <em>Incarico,Servizio,Ufficio</em></li>
                <li>le immagini che possono avere il formato <em>nomefile.ext|testo alternativo</em></li>
                <li>le date che devono avere il formato GG/MM/AAAA</li>
                <li>le date-ore che devono avere il formato GG/MM/AAAA HH:MM</li>                
                </ul>   
            </li>
            <li>
                Il formato dei campi personalizzati deve essere: < <em>nomefile.ext|titolo,nomefile2.ext|titolo2</em> >.<br />
                Dove <em>nomefile.ext</em> è il file presente nello zip e <em>titolo</em> è il titolo che si vuole dare all'oggetto creato.<br />
                I valori dei campi personalizzati sono separarti da < , >.
            </li>  
        </ul>
    </div>

    <div class="attribute-description">
        
        <h2>Seleziona quali attributi <strong>escludere</strong> nel file CSV:</h2>
        
        <table class="list">
        <tr>
        	<th class="tight"><img src={'toggle-button-16x16.gif'|ezimage} width="16" height="16" alt="{'Invert selection.'|i18n( 'design/admin/setup/cache' )}" onclick="ezjs_toggleCheckboxes( document.exportform, 'AttributeExcludeList[]' ); return false;" title="{'Invert selection.'|i18n( 'design/admin/setup/cache' )}" /></th>
    		<th>Identificatore dell'attributo</th>
    		<th>{'Datatype'|i18n( 'design/admin/setup/cache' )}</th>
        </tr>
        {foreach $attribute_fields as $name => $type}
        <tr>
        	<td><input type="checkbox" name="AttributeExcludeList[]" value="{$name}" {if $exclude_list|contains($name)} checked="checked"{/if}/></td>
        	<td>{$name}</td>
        	<td>{$type}</td>
        </tr>
        {/foreach}
        </table>
    	
    	{def $pseudoTypes = ezini( 'Settings', 'PseudoType', 'csvimport.ini' )
    		 $pseudoLocations = ezini( 'Settings', 'PseudoLocation', 'csvimport.ini' )
    		 $classes = fetch( 'class', 'list' )}
    	
    	{if count( $pseudo_fields )}
    	
	    	<h2>Campi del CSV che identificano la creazione di altri oggetti a partire da un file allegato:</h2>
	    	
	    	<table class="list">
	        <tr>
	    		<th class="tight"><img src={'toggle-button-16x16.gif'|ezimage} width="16" height="16" alt="{'Invert selection.'|i18n( 'design/admin/setup/cache' )}" onclick="ezjs_toggleCheckboxes( document.exportform, 'PseudoDelete[]' ); return false;" title="{'Invert selection.'|i18n( 'design/admin/setup/cache' )}" /></th>
	    		<th>Nome del campo CSV</th>
	    		<th>Classe</th>
	    		<th>Attributo</th>
	    		<th>Collocazione</th>
	        </tr>
	        {foreach $pseudo_fields as $class}
	        <tr>
	        	<td><input type="checkbox" name="PseudoDelete[]" value="{$class.name}"/></td>
	        	<td>
	        		{$class.string}
	        		<input type="hidden" name="PseudoFields[{$class.name}]" value="{$class.string}" />
	        	</td>
	        	<td>{$class.name}</td>
	        	<td>
	        		{$pseudoTypes[$class.type]}
	        		
	        	</td>
	        	<td>
	        		{$pseudoLocations.[$class.location]}
	        	</td>
	        </tr>
	        {/foreach}
	        </table>
	        
	        <div class="block">
	            <input class="button" type="submit" name="RemovePseudoFieldButton" value="{'Rimuovi selezionati'|i18n( 'design/admin/shop/basket' )}" />
	        </div> 
        
    	{/if}
    	
    	<h2>Aggiungi campi personalizzati per la creazione di altri oggetti a partire da file allegati:</h2>
    	
    	<table class="list">
    	<tr>
    		<tr>
	    		<th>Identificatore della classe</th>
	    		<th>Attributo</th>
	    		<th>Collocazione</th>
	        </tr>
    		<td>
	    		<select name="PseudoFieldName">
	    			<option value="" selected="selected"> - seleziona - </option>
	    		{foreach $classes as $class}
	    			 <option value="{$class.identifier}">{$class.name}</option>
	    		{/foreach}
	    		</select>
    		</td>
    		<td>
	    		<select name="PseudoFieldType">
	    			<option value="0" selected="selected"> - seleziona - </option>
	    		{foreach $pseudoTypes as $pseudoTypeValue => $pseudoTypeName}
	    			 <option value="{$pseudoTypeValue}">{$pseudoTypeName}</option>
	    		{/foreach}
	    		</select>
    		</td>
    		<td>
	    		<select name="PseudoFieldLocation">
	    			<option value="0" selected="selected"> - seleziona - </option>
	    		{foreach $pseudoLocations as $pseudoLocationValue => $pseudoLocationName}
	    			 <option value="{$pseudoLocationValue}">{$pseudoLocationName}</option>
	    		{/foreach}
	    		</select>
    		</td>
    	</tr>
    	</table>
    	<div class="block">
            <input class="button" type="submit" name="AddPseudoFieldButton" value="Aggiungi" />
        </div> 
    	
    	<input type="hidden" name="ObjectID" value="{$ObjectID}" />
        <div class="buttonblock text-right">
            <input class="defaultbutton" type="submit" name="ExportButton" value="Esporta CSV" />
        </div> 
    </div>

</div>

</div></div></div>
<div class="border-bl"><div class="border-br"><div class="border-bc"></div></div></div>
</div>

</form>
