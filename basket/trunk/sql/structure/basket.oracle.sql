
  
  CREATE SEQUENCE USER_ABS_SEQ
  START WITH 1
  MAXVALUE 999999999999999999999999999
  MINVALUE 1
  NOCYCLE
  NOCACHE
  NOORDER;
  
  
CREATE TABLE USER_ABS
(
  SYSTEM_ID     NUMBER                          NOT NULL,
  USER_ABS      VARCHAR2(32 BYTE)               ,
  NEW_USER      VARCHAR2(32 BYTE)               ,
  BASKET_ID     VARCHAR2(255 BYTE)              ,
  BASKET_OWNER  VARCHAR2(255 BYTE),
  IS_VIRTUAL    CHAR(1 BYTE)                    DEFAULT 'N'                   
)
PCTUSED    0
PCTFREE    10
INITRANS   1
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            MINEXTENTS       1
            MAXEXTENTS       2147483645
            PCTINCREASE      0
            BUFFER_POOL      DEFAULT
           )
LOGGING 
NOCOMPRESS 
NOCACHE
NOPARALLEL
MONITORING;

CREATE TABLE ACTIONS_GROUPBASKETS
(
  ID_ACTION            NUMBER                   NOT NULL,
  WHERE_CLAUSE         CLOB,
  GROUP_ID             VARCHAR2(32 BYTE)        NOT NULL,
  BASKET_ID            VARCHAR2(32 BYTE)        NOT NULL,
  USED_IN_BASKETLIST   CHAR(1 BYTE)             DEFAULT 'Y'                   ,
  USED_IN_ACTION_PAGE  CHAR(1 BYTE)             DEFAULT 'Y'                   ,
  DEFAULT_ACTION_LIST  CHAR(1 BYTE)             DEFAULT 'N'                   
)
PCTUSED    0
PCTFREE    10
INITRANS   1
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            MINEXTENTS       1
            MAXEXTENTS       2147483645
            PCTINCREASE      0
            BUFFER_POOL      DEFAULT
           )
LOGGING 
NOCOMPRESS 
NOCACHE
NOPARALLEL
MONITORING;



CREATE TABLE BASKETS
(
  COLL_ID        VARCHAR2(32 BYTE)              NOT NULL,
  BASKET_ID      VARCHAR2(32 BYTE)              NOT NULL,
  BASKET_NAME    VARCHAR2(255 BYTE)             ,
  BASKET_DESC    VARCHAR2(255 BYTE)             ,
  BASKET_CLAUSE  CLOB                           ,
  IS_GENERIC     VARCHAR2(6 BYTE)               DEFAULT 'N'                   ,
  ENABLED        CHAR(1 BYTE)                   DEFAULT 'Y'                   
)
PCTUSED    0
PCTFREE    10
INITRANS   1
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            MINEXTENTS       1
            MAXEXTENTS       2147483645
            PCTINCREASE      0
            BUFFER_POOL      DEFAULT
           )
LOGGING 
NOCOMPRESS 
NOCACHE
NOPARALLEL
MONITORING;


CREATE TABLE GROUPBASKET
(
  GROUP_ID             VARCHAR2(32 BYTE)        NOT NULL,
  BASKET_ID            VARCHAR2(32 BYTE)        NOT NULL,
  SEQUENCE             INTEGER                  DEFAULT 0                     ,
  REDIRECT_BASKETLIST  VARCHAR2(2048 BYTE)      DEFAULT NULL,
  REDIRECT_GROUPLIST   VARCHAR2(2048 BYTE)      DEFAULT NULL,
  RESULT_PAGE          VARCHAR2(255 BYTE)       DEFAULT 'SHOW_LIST1.PHP',
  CAN_REDIRECT         CHAR(1 BYTE)             DEFAULT 'N'                   ,
  CAN_DELETE           CHAR(1 BYTE)             DEFAULT 'N'                   ,
  CAN_INSERT           CHAR(1 BYTE)             DEFAULT 'N'                   
)
PCTUSED    0
PCTFREE    10
INITRANS   1
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            MINEXTENTS       1
            MAXEXTENTS       2147483645
            PCTINCREASE      0
            BUFFER_POOL      DEFAULT
           )
LOGGING 
NOCOMPRESS 
NOCACHE
NOPARALLEL
MONITORING;


CREATE OR REPLACE TRIGGER t_user_abs_ins
   BEFORE INSERT
   ON user_abs
   REFERENCING NEW AS NEW OLD AS OLD
   FOR EACH ROW
BEGIN
   SELECT user_abs_seq.NEXTVAL
     INTO :NEW.system_id
     FROM DUAL;
EXCEPTION
   WHEN OTHERS
   THEN
      RAISE;
END  t_user_abs_ins;
/
SHOW ERRORS;

ALTER TABLE USER_ABS ADD (
  CONSTRAINT USER_ABS_PKEY
 PRIMARY KEY
 (SYSTEM_ID));
 
 ALTER TABLE ACTIONS_GROUPBASKETS ADD (
  CONSTRAINT ACTIONS_GROUPBASKETS_PKEY
 PRIMARY KEY
 (ID_ACTION, GROUP_ID, BASKET_ID));


ALTER TABLE BASKETS ADD (
  CONSTRAINT BASKETS_PKEY
 PRIMARY KEY
 (COLL_ID, BASKET_ID));
 
 
ALTER TABLE GROUPBASKET ADD (
  CONSTRAINT GROUPBASKET_PKEY
 PRIMARY KEY
 (GROUP_ID, BASKET_ID));