--
-- PostgreSQL database dump
--

-- !! When you create the database be sure to use SQL_ASCII Encoding! !!


--\connect amavisnewsql amavisnewsql

SET search_path = public, pg_catalog;

--
-- TOC entry 2 (OID 82608)
-- Name: users; Type: TABLE; Schema: public; Owner: amavis
--

CREATE TABLE users (
    id serial NOT NULL,
    priority integer DEFAULT '7' NOT NULL,
    policy_id integer DEFAULT '2' NOT NULL,
    email character varying(255) NOT NULL,
    fullname character varying(255),
    digest character varying(2) DEFAULT 'WD',
    username character varying(255),
    retention integer DEFAULT 14
);


--
-- TOC entry 4 (OID 82623)
-- Name: wblist; Type: TABLE; Schema: public; Owner: amavis
--

CREATE TABLE wblist (
    rid integer NOT NULL,
    sid integer NOT NULL,
    priority integer DEFAULT 7 NOT NULL,
    email character varying(255) NOT NULL,
    wb character varying(1) NOT NULL
);


--
-- TOC entry 6 (OID 82628)
-- Name: policy; Type: TABLE; Schema: public; Owner: amavis
--

CREATE TABLE policy (
    id serial NOT NULL,
    policy_name character varying(32),
    virus_lover character(1) DEFAULT 'N',
    spam_lover character(1) DEFAULT 'N',
    banned_files_lover character(1) DEFAULT 'N',
    bad_header_lover character(1) DEFAULT 'N',
    bypass_virus_checks character(1) DEFAULT 'N',
    bypass_spam_checks character(1) DEFAULT 'N',
    bypass_banned_checks character(1) DEFAULT 'N',
    bypass_header_checks character(1) DEFAULT 'N',
    spam_modifies_subj character(1) DEFAULT 'Y',
    spam_quarantine_to character varying(64) DEFAULT 'spam-quarantine',
    spam_tag_level double precision DEFAULT -999,
    spam_tag2_level double precision,
    spam_kill_level double precision
);


--
-- TOC entry 8 (OID 82654)
-- Name: msgowner; Type: TABLE; Schema: public; Owner: amavis
--

CREATE TABLE msgowner (
    msgid serial NOT NULL,
    rid integer NOT NULL
);


--
-- TOC entry 9 (OID 82963)
-- Name: msg; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE msg (
    id serial NOT NULL,
    stype bpchar DEFAULT 'spam',
    sender character varying(255),
    subject character varying(255),
    body text,
    storetime integer,
    score real,
    CHECK (((stype = 'spam'::bpchar) OR (stype = 'virus'::bpchar)))
);


--
-- Data for TOC entry 25 (OID 82608)
-- Name: users; Type: TABLE DATA; Schema: public; Owner: amavis
--

INSERT INTO users VALUES (1, 1, 1, '@.', 'Global Match', NULL, NULL, NULL);




--
-- Data for TOC entry 27 (OID 82623)
-- Name: wblist; Type: TABLE DATA; Schema: public; Owner: amavis
--



--
-- Data for TOC entry 28 (OID 82628)
-- Name: policy; Type: TABLE DATA; Schema: public; Owner: amavis
--

INSERT INTO policy VALUES (2, 'Default', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'Y', 'spam-quarantine', -999, 4.5, 12);
INSERT INTO policy VALUES (7, 'Never Tag and Never Block', 'N', 'Y', 'N', 'N', 'N', 'N', 'N', 'N', 'Y', 'spam-quarantine', -999, 999, 999);
INSERT INTO policy VALUES (3, 'Trigger happy', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'Y', 'spam-quarantine', -999, 5, 5);
INSERT INTO policy VALUES (4, 'Permissive', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'N', 'Y', 'spam-quarantine', -999, 10, 20);
INSERT INTO policy VALUES (5, '6.5/7.8', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'Y', 'spam-quarantine', -999, 6.5, 7.8);
INSERT INTO policy VALUES (6, 'Default Tag Never Block', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'Y', 'spam-quarantine', -999, 4.5, 999);
INSERT INTO policy VALUES (1, 'Default_Nonuser', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'Y', ' ', -999, 6, 12);


--
-- Data for TOC entry 29 (OID 82654)
-- Name: msgowner; Type: TABLE DATA; Schema: public; Owner: amavis
--



--
-- Data for TOC entry 30 (OID 82963)
-- Name: msg; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 17 (OID 82712)
-- Name: users_idx_email; Type: INDEX; Schema: public; Owner: amavis
--

CREATE UNIQUE INDEX users_idx_email ON users USING btree (email);



--
-- TOC entry 18 (OID 82714)
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: amavis
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);



--
-- TOC entry 22 (OID 82718)
-- Name: policy_pkey; Type: CONSTRAINT; Schema: public; Owner: amavis
--

ALTER TABLE ONLY policy
    ADD CONSTRAINT policy_pkey PRIMARY KEY (id);


--
-- TOC entry 23 (OID 82722)
-- Name: msgowner_pkey; Type: CONSTRAINT; Schema: public; Owner: amavis
--

ALTER TABLE ONLY msgowner
    ADD CONSTRAINT msgowner_pkey PRIMARY KEY (msgid, rid);


--
-- TOC entry 21 (OID 82724)
-- Name: wblist_pkey; Type: CONSTRAINT; Schema: public; Owner: amavis
--

ALTER TABLE ONLY wblist
    ADD CONSTRAINT wblist_pkey PRIMARY KEY (rid, sid);


--
-- TOC entry 24 (OID 82971)
-- Name: msg_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY msg
    ADD CONSTRAINT msg_pkey PRIMARY KEY (id);


--
-- TOC entry 12 (OID 82606)
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: amavis
--

SELECT pg_catalog.setval ('users_id_seq', 2, true);



--
-- TOC entry 14 (OID 82626)
-- Name: policy_id_seq; Type: SEQUENCE SET; Schema: public; Owner: amavis
--

SELECT pg_catalog.setval ('policy_id_seq', 11, true);


--
-- TOC entry 15 (OID 82652)
-- Name: msgowner_msgid_seq; Type: SEQUENCE SET; Schema: public; Owner: amavis
--

SELECT pg_catalog.setval ('msgowner_msgid_seq', 1, false);


--
-- TOC entry 16 (OID 82961)
-- Name: msg_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval ('msg_id_seq', 1, false);


--
-- TOC entry 5 (OID 82623)
-- Name: COLUMN wblist.priority; Type: COMMENT; Schema: public; Owner: amavis
--

COMMENT ON COLUMN wblist.priority IS 'This use to be in mailaddr but made more sense here... so people can set their own priority levels for listed email addresses they have in common. ';


--
-- TOC entry 7 (OID 82628)
-- Name: TABLE policy; Type: COMMENT; Schema: public; Owner: amavis
--

COMMENT ON TABLE policy IS 'System Policies should be 1-10 with user policies being above id 10.  This was best way I could think to allow custom policies without showing the world each one.

You should leve id 1 and 2 alone..  here is how this works... if a user has not yet visited the SA options page they don''t have an entry in the users table.. therefore their messages should NOT be quarantined.. they will get the default policy 1 because of the catchall user @. in the users table that is associated with policy id 1.  Every other policy should be good to go with the column defaults. Allowing users to choose to enable/disable the quarantine is not an option.. that is a site wide setting. ';


--
-- TOC entry 10 (OID 82963)
-- Name: TABLE msg; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE msg IS 'This table will hold the actual quarantine messages.. it can grow quite large depending on the number of users and how long they are allowed to store messages. ';


--
-- TOC entry 11 (OID 82963)
-- Name: COLUMN msg.storetime; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN msg.storetime IS 'This is changed from a default type of timestamp to int4... to hold unix timestamps instead of native format ones... this was done to aid cross database scripting relating to time intervals.';

