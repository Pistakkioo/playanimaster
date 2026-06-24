

ALTER TABLE playanimaster_db.language_texts ADD tag varchar(100) DEFAULT null NULL;
ALTER TABLE playanimaster_db.language_texts CHANGE tag tag varchar(100) DEFAULT null NULL AFTER dt_c;


ALTER TABLE playanimaster_db.elements ADD color varchar(100) DEFAULT null NULL;


