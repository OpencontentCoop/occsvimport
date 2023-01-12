<?php

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
        'related_public_services',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Documenti';
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