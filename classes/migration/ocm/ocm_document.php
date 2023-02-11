<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_document extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'name',
        'has_code',
        'protocollo',
        'data_protocollazione',
        'image',
        'document_type',
        'abstract',
        'full_description',
        'file',
        'link',
        'attachments',
        'has_organization',
        'license',
        'format',
        'has_dataset',
        'author',
        'topics',
        'life_event',
        'business_event',
        'start_time',
        'end_time',
        'publication_start_time',
        'publication_end_time',
        'expiration_time',
        'data_di_firma',
        'other_information',
        'legal_notes',
        'reference_doc',
        'keyword',
        'has_service',
        'anno_protocollazione',
        'help',
        'tipo_di_risposta',
        'interroganti',
        'gruppo_politico',
        'data_invio_uffici',
        'data_giunta',
        'data_risposta_consigliere',
        'giorni_interrogazione',
        'data_consiglio',
        'giorni_adozione',
        'announcement_type',
        'data_di_scadenza_delle_iscrizioni',
        'data_di_conclusione',
    ];

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $attachments = function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            $data = [];
            $object = eZContentObject::fetch($content->metadata['id']);
            if ($object instanceof eZContentObject){

                $attributes = ['file_avviso', 'ammissione', 'criteri_file', 'tracce_file', 'graduatoria', 'risposta', ];
                foreach ($attributes as $attribute) {
                    $fileByAttribute = OCMigrationComunweb::getFileAttributeUrl($object, $attribute);
                    if ($fileByAttribute) {
                        $data[] = $fileByAttribute;
                    }
                }

                /** @var eZContentObject[] $embedList */
                $embedList = $object->relatedContentObjectList();
                foreach ($embedList as $embed){
                    if (in_array($embed->contentClassIdentifier(), ['file', 'file_pdf'])){
                        ocm_file::removeById($embed->attribute('remote_id'));
                        $url = OCMigrationComunweb::getFileAttributeUrl($embed);
                        if ($url){
                            $data[] = $url;
                        }
                    }
                }
            }
            $attachments = OCMigrationComunweb::getAttachmentsByNode($object->mainNode());
            foreach ($attachments as $attachment){
                ocm_file::removeById($attachment->object()->attribute('remote_id'));
                $url = OCMigrationComunweb::getFileAttributeUrl($attachment);
                if ($url){
                    $data[] = $url;
                }
            }

            return implode(PHP_EOL, $data);
        };

        $hasOrganization = function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            $data = [];
            $idList = ['area', 'servizio', 'ufficio', 'struttura',];
            foreach ($idList as $id){
                if (isset($firstLocalizedContentData[$id])){
                    foreach ($firstLocalizedContentData[$id]['content'] as $item){
                        if ($item instanceof Content) {
                            $data[] = $item->metadata['name']['ita-IT'];
                        }else{
                            $data[] = $item['name']['ita-IT'];
                        }
                    }
                }
            }

            return implode(PHP_EOL, array_unique($data));
        };

        $options['remove_ezxml_embed'] = true;

        switch ($node->classIdentifier()){
            case 'accordo':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'document_type' => function () {
                        return 'Accordi';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                ]; break;
            case 'bilancio_di_settore':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'document_type' => function () {
                        return 'Bilancio consuntivo';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                ]; break;
            case 'bando':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'document_type' => function () {
                        return 'Bando di gara';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'link' => OCMigration::getMapperHelper('link'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'other_information' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $fase = OCMigration::getMapperHelper('fase')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return $fase;
                    },
                    'protocollo' => OCMigration::getMapperHelper('numero_protocollo_bando'),
                    'data_protocollazione' => OCMigration::getMapperHelper('anno_protocollo_bando'),
                    'announcement_type' => OCMigration::getMapperHelper('tipologia_bando'),
                ]; break;
            case 'circolare':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'document_type' => function () {
                        return 'Circolare';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('modulistica'),
                ]; break;
            case 'concorso':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'document_type' => function () {
                        return 'Bando di concorso';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'link' => OCMigration::getMapperHelper('link'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'other_information' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $criteri = OCMigration::getMapperHelper('criteri')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $tracce = OCMigration::getMapperHelper('tracce')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $assunti = OCMigration::getMapperHelper('assunti')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        if (!empty($assunti)){
                            $assunti = "<p>Numero assunti:$assunti</p>";
                        }
                        $spese = OCMigration::getMapperHelper('spese')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        if (!empty($spese)){
                            $spese = "<p>Numero assunti:$spese</p>";
                        }

                        return $criteri.$tracce.$assunti.$spese;
                    },
                ]; break;
            case 'concessioni':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'has_code' => OCMigration::getMapperHelper('numero'),
                    'document_type' => function () {
                        return 'Concessione';
                    },
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                ]; break;
            case 'convenzione':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'abstract' => OCMigration::getMapperHelper('short_description'),
                    'document_type' => function () {
                        return 'Convenzione';
                    },
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'publication_end_time' => OCMigration::getMapperHelper('data_finepubblicazione'),
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'protocollo' => OCMigration::getMapperHelper('numero_protocollo'),
                    'data_protocollazione' => OCMigration::getMapperHelper('anno_protocollo'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                ]; break;
            case 'decreto_sindacale':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'has_code' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $numero = OCMigration::getMapperHelper('numero')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $anno = OCMigration::getMapperHelper('anno')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return "$numero/$anno";
                    },
                    'document_type' => function () {
                        return 'Decreto sindacale';
                    },
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'publication_end_time' => OCMigration::getMapperHelper('data_finepubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'data_di_firma' => OCMigration::getMapperHelper('data'),
                    'protocollo' => OCMigration::getMapperHelper('numero_protocollo'),
                    'data_protocollazione' => OCMigration::getMapperHelper('anno_protocollo'),
                ]; break;
            case 'deliberazione':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'has_code' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $numero = OCMigration::getMapperHelper('numero')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $anno = OCMigration::getMapperHelper('anno')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return "$numero/$anno";
                    },
                    'abstract' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $informazioni_esecutivita = OCMigration::getMapperHelper('informazioni_esecutivita')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        if (!empty($informazioni_esecutivita)){
                            $informazioni_esecutivita = '<p><b>Informazioni riguardanti l\'esecutività della delibera</b></p><p>'.$informazioni_esecutivita.'</p>';
                        }
                        $stato = OCMigration::getMapperHelper('stato')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        if (!empty($stato)){
                            $stato = '<p><b>Stato in cui si trova la delibera</b></p><p>'.$informazioni_esecutivita.'</p>';
                        }
                        $pubblicazione = OCMigration::getMapperHelper('pubblicazione')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        if (!empty($pubblicazione)){
                            $pubblicazione = '<p><b>Informazioni sulla pubblicazione della delibera</b></p><p>'.$informazioni_esecutivita.'</p>';
                        }

                        return $informazioni_esecutivita.$stato.$pubblicazione;
                    },
                    'document_type' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $organo = OCMigration::getMapperHelper('organo_competente')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        if (stripos($organo, 'consi')){
                            return 'Deliberazione del Consiglio comunale';
                        }
                        if (stripos($organo, 'giunt')){
                            return 'Deliberazione della Giunta comunale';
                        }
                        if (stripos($organo, 'commiss')){
                            return 'Deliberazione del Commissario ad acta';
                        }
                        return 'Deliberazione di altri Organi';
                    },
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'publication_end_time' => OCMigration::getMapperHelper('data_finepubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'data_di_firma' => OCMigration::getMapperHelper('data'),
                    'start_time' => OCMigration::getMapperHelper('data_esecutivita'),
                    'protocollo' => OCMigration::getMapperHelper('numero_protocollo'),
                    'data_protocollazione' => OCMigration::getMapperHelper('anno_protocollo'),
                ]; break;
            case 'determinazione':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'has_code' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $numero = OCMigration::getMapperHelper('numero')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $anno = OCMigration::getMapperHelper('anno')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return "$numero/$anno";
                    },
                    'document_type' => function () {
                        return 'Determinazione';
                    },
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'publication_end_time' => OCMigration::getMapperHelper('data_finepubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'data_di_firma' => OCMigration::getMapperHelper('data_firma'),
                    'start_time' => OCMigration::getMapperHelper('data_efficacia'),
                    'protocollo' => OCMigration::getMapperHelper('numero_protocollo'),
                    'data_protocollazione' => OCMigration::getMapperHelper('anno_protocollo'),
                ]; break;
            case 'documento':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'document_type' => function () {
                        return '';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'link' => OCMigration::getMapperHelper('link'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data'),
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('riferimento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                    'other_information' => OCMigration::getMapperHelper('iter_approvazione'),
                ]; break;
            case 'graduatoria':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'document_type' => function () {
                        return 'Graduatoria';
                    },
                    'abstract' => OCMigration::getMapperHelper('short_description'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'link' => OCMigration::getMapperHelper('link'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                ]; break;
            case 'interrogazione':
            case 'interpellanza':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'has_code' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $numero = OCMigration::getMapperHelper('numero')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $anno = OCMigration::getMapperHelper('anno')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return "$numero";
                    },
                    'document_type' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        return ucfirst($content->metadata->classIdentifier);
                    },
                    'full_description' => OCMigration::getMapperHelper('soggetti'),
                    'file' => OCMigration::getMapperHelper('testo'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'other_information' => OCMigration::getMapperHelper('note'),
                    'data_invio_uffici' => OCMigration::getMapperHelper('data_invio_uffici'),
                    'data_giunta' => OCMigration::getMapperHelper('data_giunta'),
                    'data_risposta_consigliere' => OCMigration::getMapperHelper('data_risposta_consigliere'),
                    'giorni_interrogazione' => OCMigration::getMapperHelper('giorni_interrogazione'),
                    'data_consiglio' => OCMigration::getMapperHelper('data_consiglio'),
                    'data_protocollazione' => OCMigration::getMapperHelper('data_protocollo'),
                    'giorni_adozione' => OCMigration::getMapperHelper('giorni_adozione'),
                ]; break;
            case 'mozione':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'has_code' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $numero = OCMigration::getMapperHelper('numero')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $anno = OCMigration::getMapperHelper('anno')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return "$numero";
                    },
                    'document_type' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        return ucfirst($content->metadata->classIdentifier);
                    },
                    'full_description' => OCMigration::getMapperHelper('soggetti'),
                    'file' => OCMigration::getMapperHelper('testo'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'data_invio_uffici' => OCMigration::getMapperHelper('data_invio_uffici'),
                    'data_giunta' => OCMigration::getMapperHelper('data_giunta'),
                    'data_risposta_consigliere' => OCMigration::getMapperHelper('data_risposta_consigliere'),
                    'giorni_interrogazione' => OCMigration::getMapperHelper('giorni_interrogazione'),
                    'data_consiglio' => OCMigration::getMapperHelper('data_consiglio'),
                    'data_protocollazione' => OCMigration::getMapperHelper('data_protocollo'),
                    'giorni_adozione' => OCMigration::getMapperHelper('giorni_adozione'),
                    'other_information' => OCMigration::getMapperHelper('note_aggiuntive'),
                ]; break;
            case 'modello':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'document_type' => function () {
                        return 'Modulistica';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'link' => OCMigration::getMapperHelper('link'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                    'other_information' => OCMigration::getMapperHelper('note'),
                ]; break;
            case 'modulo':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'has_code' => OCMigration::getMapperHelper('codice'),
                    'document_type' => function () {
                        return 'Modulistica';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'link' => OCMigration::getMapperHelper('link'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                ]; break;
            case 'modulistica':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'has_code' => OCMigration::getMapperHelper('codice'),
                    'document_type' => function () {
                        return 'Modulistica';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'link' => OCMigration::getMapperHelper('link'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                ]; break;
            case 'normativa':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'document_type' => function () {
                        return 'Normativa';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'link' => OCMigration::getMapperHelper('link'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                ]; break;
            case 'ordinanza':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'has_code' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $numero = OCMigration::getMapperHelper('numero')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $anno = OCMigration::getMapperHelper('anno')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return "$numero/$anno";
                    },
                    'document_type' => function () {
                        return 'Ordinanza';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'publication_end_time' => OCMigration::getMapperHelper('data_finepubblicazione'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('riferimento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                    'other_information' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $isUrgenza = OCMigration::getMapperHelper('urgenza')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );
                        $urgenza = $isUrgenza ? '<p>Ordinanza emanata in deroga alla legislazione vigente</p>' : '';
                        $motivo_non_pubblicazione = OCMigration::getMapperHelper('motivo_non_pubblicazione')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return $urgenza.$motivo_non_pubblicazione;
                    },
                    'protocollo' => OCMigration::getMapperHelper('numero_protocollo'),
                    'data_protocollazione' => OCMigration::getMapperHelper('anno_protocollo'),
                ]; break;
            case 'ordine_del_giorno':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('oggetto'),
                    'has_code' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        $numero = OCMigration::getMapperHelper('numero')(
                            $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                        );

                        return "$numero";
                    },
                    'document_type' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                        return 'Ordine del giorno';
                    },
                    'full_description' => OCMigration::getMapperHelper('soggetti'),
                    'file' => OCMigration::getMapperHelper('testo'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'data_invio_uffici' => OCMigration::getMapperHelper('data_invio_uffici'),
                    'data_giunta' => OCMigration::getMapperHelper('data_giunta'),
                    'data_risposta_consigliere' => OCMigration::getMapperHelper('data_risposta_consigliere'),
                    'giorni_interrogazione' => OCMigration::getMapperHelper('giorni_interrogazione'),
                    'data_consiglio' => OCMigration::getMapperHelper('data_consiglio'),
                    'data_protocollazione' => OCMigration::getMapperHelper('data_protocollo'),
                    'giorni_adozione' => OCMigration::getMapperHelper('giorni_adozione'),
                ]; break;
            case 'parere':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('title'),
                    'document_type' => function () {
                        return 'Parere';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('description'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'other_information' => OCMigration::getMapperHelper('firma'),
                ]; break;
            case 'piano_progetto':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'has_code' => OCMigration::getMapperHelper('codice'),
                    'document_type' => function () {
                        return 'Piano/Progetto';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('documento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                ]; break;
//            case 'procedura': // solo consorzio 5
//            case 'protocollo': // solo asia 1 borgochiese 2 condino 2 consorzio 4 dambel 1 molveno 1 nagotorbole 2
//            case 'rapporto': // non usato
            case 'regolamento':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('titolo'),
                    'has_code' => OCMigration::getMapperHelper('codice'),
                    'document_type' => function () {
                        return 'Regolamento';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                    'has_organization' => $hasOrganization,
                    'publication_start_time' => OCMigration::getMapperHelper('data_iniziopubblicazione'),
                    'start_time' => OCMigration::getMapperHelper('data_inizio_validita'),
                    'end_time' => OCMigration::getMapperHelper('data_fine_validita'),
                    'expiration_time' => OCMigration::getMapperHelper('data_archiviazione'),
                    'reference_doc' => OCMigration::getMapperHelper('riferimento'),
                    'keyword' => OCMigration::getMapperHelper('parola_chiave'),
                ]; break;
            case 'statuto':
                $mapper = [
                    'name' => OCMigration::getMapperHelper('name'),
                    'document_type' => function () {
                        return 'Statuto';
                    },
                    'abstract' => OCMigration::getMapperHelper('abstract'),
                    'full_description' => OCMigration::getMapperHelper('descrizione'),
                    'file' => OCMigration::getMapperHelper('file'),
                    'attachments' => $attachments,
                ]; break;
//            case 'trattamento': // npn utilizzato
            default:
                $mapper = [];
        }

        return $this->fromNode($node, $mapper, $options);
    }


    public static function getSpreadsheetTitle(): string
    {
        return 'Documenti';
    }

    public static function getColumnName(): string
    {
        return "Titolo*";
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificativo del documento*";
    }

    public function toSpreadsheet(): array
    {
//        $rawAttachments = explode('|', $this->attribute('attachments'));
//        $attachments = [];
//        foreach ($rawAttachments as $rawAttachment){
//            $parts = explode('/', $rawAttachment);
//            $base = array_pop($parts);
//            $base = urlencode($base);
//            $parts[] = $base;
//            $attachments[] = implode('/', $parts);
//        }

        return [
            "Identificativo del documento*" => $this->attribute('_id'),
            "Titolo*" => $this->attribute('name'),
            "Protocollo*" => $this->attribute('has_code'),
            "Data protocollazione*" => $this->attribute('data_protocollazione'),
            "Tipo di documento*" => $this->attribute('document_type'),
            "Argomento*" => $this->attribute('topics'),
            "Descrizione breve*" => $this->attribute('abstract'),
            "URL documento*" => $this->attribute('file'),
            "Licenza di distribuzione*" => $this->attribute('license'),
            "Formati disponibili*" => $this->attribute('format'),
            "Ufficio responsabile del documento*" => $this->attribute('has_organization'),
            "Descrizione" => $this->attribute('full_description'),
            "Link esterno al documento" => $this->attribute('link'),
            "File allegati" => $this->attribute('attachments'),
//            "URL file allegato 1" => $attachments[0] ?? '',
//            "URL file allegato 2" => $attachments[1] ?? '',
//            "URL file allegato 3" => $attachments[2] ?? '',
//            "URL file allegato 4" => isset($attachments[3]) ? str_replace('|', PHP_EOL, $attachments[3]) : '',
            "Data di inizio validità" => $this->attribute('start_time'),
            "Data di fine validità" => $this->attribute('end_time'),
            "Data di inizio pubblicazione" => $this->attribute('publication_start_time'),
            "Data di fine pubblicazione" => $this->attribute('publication_end_time'),
            "Data di rimozione" => $this->attribute('expiration_time'),
            "Data di firma" => $this->attribute('data_di_firma'),
            "Dataset" => $this->attribute('has_dataset'),
            "Ulteriori informazioni" => $this->attribute('other_information'),
            "Riferimenti normativi" => $this->attribute('legal_notes'),
            "Documenti collegati" => $this->attribute('reference_doc'),
            "Parola chiave" => $this->attribute('keyword'),
            "Evento della vita" => $this->attribute('life_event'),
            "Evento aziendale" => $this->attribute('business_event'),
            "Autore/i" => $this->attribute('author'),
            "Immagine" => $this->attribute('image'),
            "Tipo di risposta" => $this->attribute('tipo_di_risposta'),
            "Interroganti" => $this->attribute('interroganti'),
            "Gruppo consiliare" => $this->attribute('gruppo_politico'),
            "Data di invio agli uffici" => $this->attribute('data_invio_uffici'),
            "Data di passaggio in Giunta" => $this->attribute('data_giunta'),
            "Data di risposta al consigliere" => $this->attribute('data_risposta_consigliere'),
            "Giorni per la risposta" => $this->attribute('giorni_interrogazione'),
            "Data trattazione/risposta in Consiglio" => $this->attribute('data_consiglio'),
            "Giorni per l'adozione" => $this->attribute('giorni_adozione'),
            "Tipologia di bando" => $this->attribute('announcement_type'),
            "Data di scadenza delle iscrizioni" => $this->attribute('data_di_scadenza_delle_iscrizioni'),
            "Data di conclusione del bando/progetto" => $this->attribute('data_di_conclusione'),
            "Servizi" => $this->attribute('related_public_services'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function getDateValidationHeaders(): array
    {
        return [
            "Data di inizio validità",
            "Data di fine validità",
            "Data di inizio pubblicazione",
            "Data di fine pubblicazione",
            "Data di rimozione",
            "Data di firma",
            "Data di invio agli uffici",
            "Data di passaggio in Giunta",
            "Data di risposta al consigliere",
            "Data trattazione/risposta in Consiglio",
            "Data di scadenza delle iscrizioni",
            "Data di conclusione del bando/progetto"
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            "Tipo di documento*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('documenti'),
            ],
            "Argomento*" => [
                'strict' => false,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
            "Licenza di distribuzione*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('licenze'),
            ],
            "Formati disponibili*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('formati'),
            ],
            "Ufficio responsabile del documento*" => [
                'strict' => false,
                'ref' => ocm_organization::getRangeRef()
            ],
            "Documenti collegati" => [
                'strict' => false,
                'ref' => ocm_document::getRangeRef()
            ],
            "Evento della vita" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('life-events'),
            ],
            "Evento aziendale" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('business-events'),
            ],
            "Immagine" => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef()
            ],
            "Interroganti" => [
                'strict' => false,
                'ref' => ocm_public_person::getRangeRef()
            ],
            "Servizi" => [
                'strict' => false,
                'ref' => ocm_public_service::getRangeRef()
            ],
        ];
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve*",
            "Descrizione",
            "Ulteriori informazioni",
            "Riferimenti normativi",
        ];
    }


    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo del documento*"]);
        $item->setAttribute('name', $row["Titolo*"]);
        $item->setAttribute('has_code', $row["Protocollo*"]);
        $item->setAttribute('data_protocollazione', $row["Data protocollazione*"]);
        $item->setAttribute('document_type', $row["Tipo di documento*"]);
        $item->setAttribute('topics', $row["Argomento*"]);
        $item->setAttribute('abstract', $row["Descrizione breve*"]);
        $item->setAttribute('file', $row["URL documento*"]);
        $item->setAttribute('license', $row["Licenza di distribuzione*"]);
        $item->setAttribute('format', $row["Formati disponibili*"]);
        $item->setAttribute('has_organization', $row["Ufficio responsabile del documento*"]);
        $item->setAttribute('full_description', $row["Descrizione"]);
        $item->setAttribute('link', $row["Link esterno al documento"]);
        $item->setAttribute('attachments', implode('|', $row["File allegati"]));
//        $item->setAttribute('attachments_1', $row["URL file allegato 1"]);
//        $item->setAttribute('attachments_2', $row["URL file allegato 2"]);
//        $item->setAttribute('attachments_3', $row["URL file allegato 3"]);
//        $item->setAttribute('attachments_4', $row["URL file allegato 4"]);
        $item->setAttribute('start_time', $row["Data di inizio validità"]);
        $item->setAttribute('end_time', $row["Data di fine validità"]);
        $item->setAttribute('publication_start_time', $row["Data di inizio pubblicazione"]);
        $item->setAttribute('publication_end_time', $row["Data di fine pubblicazione"]);
        $item->setAttribute('expiration_time', $row["Data di rimozione"]);
        $item->setAttribute('data_di_firma', $row["Data di firma"]);
        $item->setAttribute('has_dataset', $row["Dataset"]);
        $item->setAttribute('other_information', $row["Ulteriori informazioni"]);
        $item->setAttribute('legal_notes', $row["Riferimenti normativi"]);
        $item->setAttribute('reference_doc', $row["Documenti collegati"]);
        $item->setAttribute('keyword', $row["Parola chiave"]);
        $item->setAttribute('life_event', $row["Evento della vita"]);
        $item->setAttribute('business_event', $row["Evento aziendale"]);
        $item->setAttribute('author', $row["Autore/i"]);
        $item->setAttribute('image', $row["Immagine"]);
        $item->setAttribute('tipo_di_risposta', $row["Tipo di risposta"]);
        $item->setAttribute('interroganti', $row["Interroganti"]);
        $item->setAttribute('gruppo_politico', $row["Gruppo consiliare"]);
        $item->setAttribute('data_invio_uffici', $row["Data di invio agli uffici"]);
        $item->setAttribute('data_giunta', $row["Data di passaggio in Giunta"]);
        $item->setAttribute('data_risposta_consigliere', $row["Data di risposta al consigliere"]);
        $item->setAttribute('giorni_interrogazione', $row["Giorni per la risposta"]);
        $item->setAttribute('data_consiglio', $row["Data trattazione/risposta in Consiglio"]);
        $item->setAttribute('giorni_adozione', $row["Giorni per l'adozione"]);
        $item->setAttribute('announcement_type', $row["Tipologia di bando"]);
        $item->setAttribute('data_di_scadenza_delle_iscrizioni', $row["Data di scadenza delle iscrizioni"]);
        $item->setAttribute('data_di_conclusione', $row["Data di conclusione del bando/progetto"]);

        return $item;
    }

    public static function getImportPriority(): int
    {
        return 100;
    }

    public function generatePayload(): array
    {
        return [];
    }
}