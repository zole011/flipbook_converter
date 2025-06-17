--
-- Tabela: tx_flipbookconverter_document
--
CREATE TABLE tx_flipbookconverter_document (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    
    -- Core polja
    title varchar(255) DEFAULT '' NOT NULL,
    description text,
    pdf_file int(11) unsigned DEFAULT '0' NOT NULL,
    
    -- Processing informacije
    status tinyint(3) unsigned DEFAULT '0' NOT NULL,
    processed_images text,
    processing_log text,
    total_pages int(11) unsigned DEFAULT '0' NOT NULL,
    file_size bigint(20) unsigned DEFAULT '0' NOT NULL,
    file_hash varchar(64) DEFAULT '' NOT NULL,
    
    -- Konfiguracija (JSON format)
    flipbook_config text,
    
    -- Performance data
    processing_time int(11) DEFAULT '0' NOT NULL,
    last_processed int(11) unsigned DEFAULT '0' NOT NULL,
    
    -- TYPO3 standard polja
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
    hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
    starttime int(11) unsigned DEFAULT '0' NOT NULL,
    endtime int(11) unsigned DEFAULT '0' NOT NULL,
    
    -- Sortiranje i kategorije
    sorting int(11) DEFAULT '0' NOT NULL,
    fe_group varchar(255) DEFAULT '0' NOT NULL,
    
    -- Workspace podrška
    t3ver_oid int(11) DEFAULT '0' NOT NULL,
    t3ver_wsid int(11) DEFAULT '0' NOT NULL,
    t3ver_state tinyint(4) DEFAULT '0' NOT NULL,
    t3ver_stage int(11) DEFAULT '0' NOT NULL,
    
    -- Jezik podrška
    sys_language_uid int(11) DEFAULT '0' NOT NULL,
    l10n_parent int(11) DEFAULT '0' NOT NULL,
    l10n_source int(11) DEFAULT '0' NOT NULL,
    l10n_diffsource mediumblob,
    
    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY status (status),
    KEY file_hash (file_hash),
    KEY language (l10n_parent,sys_language_uid),
    KEY version (t3ver_oid,t3ver_wsid),
    KEY processing (status,last_processed)
);

--
-- Proširenje tt_content tabele za FlexForm data
--
CREATE TABLE tt_content (
    tx_flipbookconverter_document int(11) unsigned DEFAULT '0' NOT NULL,
    KEY flipbook_document (tx_flipbookconverter_document)
);

--
-- Tabela za processing queue (optional - za batch processing)
--
CREATE TABLE tx_flipbookconverter_queue (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    
    document_uid int(11) unsigned DEFAULT '0' NOT NULL,
    priority tinyint(3) unsigned DEFAULT '1' NOT NULL,
    status tinyint(3) unsigned DEFAULT '0' NOT NULL,
    
    scheduled_time int(11) unsigned DEFAULT '0' NOT NULL,
    started_time int(11) unsigned DEFAULT '0' NOT NULL,
    finished_time int(11) unsigned DEFAULT '0' NOT NULL,
    
    retry_count tinyint(3) unsigned DEFAULT '0' NOT NULL,
    max_retries tinyint(3) unsigned DEFAULT '3' NOT NULL,
    
    error_message text,
    processing_data text,
    
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    
    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY document (document_uid),
    KEY status_priority (status,priority),
    KEY scheduled (scheduled_time)
);

--
-- Tabela za analytics/usage tracking (optional)
--
CREATE TABLE tx_flipbookconverter_statistics (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    
    document_uid int(11) unsigned DEFAULT '0' NOT NULL,
    page_uid int(11) unsigned DEFAULT '0' NOT NULL,
    
    views int(11) unsigned DEFAULT '0' NOT NULL,
    unique_views int(11) unsigned DEFAULT '0' NOT NULL,
    total_time_spent int(11) unsigned DEFAULT '0' NOT NULL,
    pages_viewed text,
    
    last_viewed int(11) unsigned DEFAULT '0' NOT NULL,
    
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    
    PRIMARY KEY (uid),
    KEY document_stats (document_uid,last_viewed),
    KEY page_stats (page_uid,last_viewed)
);

CREATE TABLE tt_content (
    tx_flipbookconverter_document int(11) unsigned DEFAULT '0' NOT NULL,
    KEY flipbook_document (tx_flipbookconverter_document)
);