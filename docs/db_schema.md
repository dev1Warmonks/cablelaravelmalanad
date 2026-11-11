# DB schema snapshot (local copy cablebill02nov)

## actdeactlog (BASE TABLE) rows=0
id int PK auto_increment, subscriberid varchar(11), status varchar(1), description varchar(50), timestamp varchar(40)

## adminbilling (BASE TABLE) rows=0
id int PK auto_increment, amount decimal(20,4), description varchar(150), openingbalance decimal(20,4), debit decimal(20,4), credit decimal(20,4), closingbalance decimal(20,4), timestamp varchar(50)

## agent (BASE TABLE) rows=115
id int PK auto_increment, emptype varchar(50), name varchar(30), mobile varchar(10), username varchar(45), password varchar(200), areaid varchar(250), salary varchar(10), timestamp varchar(50), status int, created_by int, created_on int, token varchar(45)

## agent_areas (BASE TABLE) rows=577
id int PK auto_increment, agent_id int, agent_area_id varchar(45)

## agentbilling (BASE TABLE) rows=0
id int PK, agentid varchar(11), amount decimal(20,4), description varchar(150), openingbalance decimal(20,4), debit decimal(20,4), credit decimal(20,4), closingbalance decimal(20,4), timestamp varchar(50)

## agentwallettxn (BASE TABLE) rows=0
id int PK, subscriberidid varchar(11), amount decimal(20,4), description varchar(150), openingbalance decimal(20,4), debit decimal(20,4), credit decimal(20,4), closingbalance decimal(20,4), timestamp varchar(50)

## attendanceregister (BASE TABLE) rows=0
id int PK, agentid varchar(11), title varchar(255), start varchar(50), end varchar(50), url varchar(255), backgroundColor varchar(25), borderColor varchar(25), allDay varchar(255), advanceamt varchar(10), advancedesc varchar(300), timestamp varchar(50)

## balance_cross (VIEW)
```sql
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `balance_cross` AS select `m1`.`subscriberid` AS `subscriberid`,`m1`.`dbafterdiscount` AS `dbafterdiscount` from (`subscriberpayments` `m1` left join `subscriberpayments` `m2` on(((`m1`.`subscriberid` = `m2`.`subscriberid`) and (`m1`.`id` < `m2`.`id`)))) where (`m2`.`id` is null)
```

## bills (BASE TABLE) rows=0
id int PK, subscriberid varchar(11), subscriptionmonth varchar(50), previousdueamount decimal(10,4), subscriptionamount decimal(10,4), newdueamount decimal(10,4), timestamp varchar(40)

## broadband (BASE TABLE) rows=0
id int PK, retailerid varchar(11), amount varchar(10), timestamp varchar(40)

## client (BASE TABLE) rows=0
id int, domainname int, mobile int

## complaintregister (BASE TABLE) rows=0
id int PK, subscriberid varchar(11), agentid varchar(11), technicianid varchar(11), description varchar(250), status varchar(1), code varchar(6), timestamp varchar(40), closetimestamp varchar(40)

## empsalary (BASE TABLE) rows=0
id int PK, agentid varchar(11), salary varchar(10), timestamp varchar(50)

## expenseheader (BASE TABLE) rows=40
id int PK auto_increment, title varchar(50), status int, type varchar(1), created_by int, created_on datetime

## expenses (BASE TABLE) rows=181
id int PK auto_increment, expenseid varchar(11), amount varchar(10), remarks varchar(500), created_by int, created_on datetime, expensedate date

## ezybill_customers (BASE TABLE) rows=51297
id int PK auto_increment, customer_id varchar(45), caf_number varchar(45), lcoCode varchar(45), customer_name varchar(105), lco_customer_id varchar(45), mobile varchar(15), billing_address text, serial_number varchar(405), vc_number varchar(405), created_on datetime, created_by int, import_id int, updated_on datetime, updated_by int, pincode varchar(10), stb_count int, city varchar(100), installation_date datetime, reseller_business_name varchar(200), newid text

## gsoft_billing (BASE TABLE) rows=1101
id int PK auto_increment, lco_code varchar(100), current_month varchar(200), invoice_generated int, free_sms_used int, extra_sms_used int, invoice_total decimal(10,0), created_on datetime, payment_type int, tran_id varchar(200), bill_status int

## gsoft_payment_types (BASE TABLE) rows=4
id int PK auto_increment, name varchar(100), url varchar(500), image_url varchar(250), status int, qrcode_url varchar(500)

## import_format (BASE TABLE) rows=23
id int PK auto_increment, field_name varchar(200), column_no int, type int, excel_column_name varchar(200)

## invoice_history (VIEW)
```sql
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `invoice_history` AS select date_format(`m_ezybill_invoices`.`current_month`,'%Y - %M') AS `yearmonth`,`m_ezybill_invoices`.`created_by` AS `created_by`,`m_ezybill_invoices`.`current_month` AS `current_month` from `m_ezybill_invoices` group by `m_ezybill_invoices`.`created_by`,`m_ezybill_invoices`.`current_month`
```

## invoice_import_history (BASE TABLE) rows=2437
id int PK auto_increment, file_name varchar(45), created_on datetime

## m_area (BASE TABLE) rows=63
id int PK auto_increment, area_code varchar(45), areaname varchar(50), status int, created_by int, created_on datetime

## m_broadband_customer (BASE TABLE) rows=9
id int PK auto_increment, customer_id int, username varchar(45), password varchar(45), router_make varchar(45), router_ip varchar(45), router_password varchar(45), wifi_ssid varchar(45), wifi_password varchar(45), vlan varchar(45), isp_type int, broadband_plan int, bsnl_voice_vlan varchar(45), bsnl_voice_password varchar(45), bsnl_voice_ip varchar(45), status int

## m_broadband_isp_type (BASE TABLE) rows=2
id int PK auto_increment, name varchar(45), status int

## m_broadband_plan (BASE TABLE) rows=2
id int PK auto_increment, name varchar(45), period int, isp_rate decimal(10,2), tax_rate decimal(10,2), operator_rate decimal(10,2), package_amount decimal(10,2), description varchar(45), status int

## m_combo_plan (BASE TABLE) rows=62
id int PK auto_increment, name varchar(45), rate decimal(10,2), description varchar(200), status int

## m_complaints (BASE TABLE) rows=33
id int PK auto_increment, customer_id int, network_id int, type int, description varchar(450), created_by int, technician_id int, technician_remarks varchar(45), o_message varchar(1000), t_message varchar(1000), c_message varchar(1000), o_response varchar(250), t_response varchar(250), c_response varchar(250), created_on datetime, accepted_on datetime, resolved_on datetime, status int

## m_customer (BASE TABLE) rows=16234
id int PK auto_increment, customer_id varchar(45), caf_number varchar(45), lcoCode varchar(45), customer_name varchar(105), lco_customer_id varchar(45), area_id int, subarea_id int, mobile varchar(15), billing_address text, opening_balance decimal(10,2), created_on datetime, created_by int, status int, enable_app int, enable_bill_payment int, import_id int, updated_on datetime, updated_by int, serial_number varchar(405), vc_number varchar(405), sms int, combo_plan int, amc_status int, amc_end_date date, newid text, c_type int

## m_customer_packages (BASE TABLE) rows=0
id int PK auto_increment, customer_id int, package_id int

## m_dropdown (BASE TABLE) rows=26
id int PK auto_increment, name varchar(200), status int, type int

## m_enquiries (BASE TABLE) rows=0
id int PK auto_increment, network_id int, type int, name varchar(150), house_name varchar(150), address varchar(150), mobile varchar(12), description varchar(450), created_by int, technician_id int, technician_remarks varchar(45), o_message varchar(1000), t_message varchar(1000), c_message varchar(1000), o_response varchar(250), t_response varchar(250), c_response varchar(250), created_on datetime, accepted_on datetime, resolved_on datetime, token varchar(20), status int

## m_ezybill_invoices (BASE TABLE) rows=615096
id int PK auto_increment, customer_id varchar(45), package varchar(4000), serial_number varchar(1000), vc_number varchar(1000), invoice_date date, bill_amount decimal(10,2), discount_amount decimal(10,2), tax_amount decimal(10,2), cgst decimal(10,2), sgst decimal(10,2), cess decimal(10,2), mso_share decimal(10,2), lco_share decimal(10,2), total_amount decimal(10,2), current_bill_amount decimal(10,2), current_month varchar(45), import_id int, created_on datetime, created_by int, status int, updated_on datetime, updated_by int, no_of_days int, sms_status int, sms varchar(450), sms_id varchar(500), gateway int

## m_main_menus (BASE TABLE) rows=14
menu_id int PK auto_increment, menu_name varchar(450)

## m_network (BASE TABLE) rows=36
id int PK auto_increment, network_name varchar(45), lcoCode varchar(45), address varchar(200), postal_licence_number varchar(45), logo varchar(500), status int, created_by int, created_on datetime, print int, phone varchar(10), sms_signature varchar(45), bill_type int, bill_extra_amount decimal(10,2)

## m_package (BASE TABLE) rows=0
id int PK auto_increment, name varchar(45), total_amount decimal(10,2), status int

## m_package_channels (BASE TABLE) rows=0
id int PK auto_increment, package_id int, product_id int, created_by int, created_on datetime

## m_products (BASE TABLE) rows=240
id int PK auto_increment, product_name varchar(45), category int, Is_alacarte int, channels int, pricing_structure int, base_rate decimal(10,2), tax_rate decimal(10,2), total_amount decimal(10,2), bundle_total decimal(10,2), status int, import_id int, updated_on datetime, updated_by int

## m_products_category (BASE TABLE) rows=3
id int PK auto_increment, name varchar(45), status int

## m_stb (BASE TABLE) rows=0
id int PK auto_increment, customer_id int, serial_number varchar(45), type int, make int, model int, install_date date, caf_no varchar(45), vewing_card_no varchar(45), cas_provider int, status int, created_by int

## m_stb_cas (BASE TABLE) rows=2
id int PK auto_increment, name varchar(45), status int

## m_stb_make (BASE TABLE) rows=1
id int PK auto_increment, name varchar(45), status varchar(45)

## m_stb_model (BASE TABLE) rows=8
id int PK auto_increment, name varchar(45), status int

## m_stb_type (BASE TABLE) rows=5
id int PK auto_increment, name varchar(45), status varchar(45)

## m_sub_area (BASE TABLE) rows=347
id int PK auto_increment, area_id int, areaname varchar(50), last_created_number varchar(45), status int

## m_sub_area_view (VIEW)
```sql
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `m_sub_area_view` AS select `a`.`id` AS `sub_area_id`,`a`.`area_id` AS `area_id`,`a`.`areaname` AS `sub_area_name`,`a`.`status` AS `status`,`b`.`areaname` AS `areaname` from (`m_sub_area` `a` left join `m_area` `b` on((`b`.`id` = `a`.`area_id`)))
```

## m_tax_rate (BASE TABLE) rows=0
id int PK auto_increment, tax int, status int, description varchar(45)

## m_user (BASE TABLE) rows=26
id int PK auto_increment, network_name varchar(150), username varchar(150), password varchar(150), sms int, misc_add_sms int, misc_payment int, smscount varchar(25), type int, status int, timestamp varchar(50), network_count int, extra_sms_price decimal(10,2), per_customer_price decimal(10,2), max_pending_invoice int, block_network int, default_sms_api int, join_date date

## m_user_roles (BASE TABLE) rows=188
id int PK auto_increment, user_id int, menu_id int

## mobile (BASE TABLE) rows=0
id int PK, subscriberid varchar(10), operatorcode varchar(5), number varchar(15), amount varchar(10), status varchar(2), requestid varchar(50), message varchar(50), transactionid varchar(50), type int, opbalance decimal(10,4), debit decimal(10,4), credit decimal(10,4), balance decimal(10,4), ttimestamp varchar(40), isrefunded varchar(1), requrl varchar(250), response varchar(800)

## nodes (BASE TABLE) rows=0
id int PK auto_increment, name varchar(50), status int

## package (BASE TABLE) rows=0
id int PK auto_increment, name varchar(50), period varchar(5), amount varchar(10), status int

## settings (BASE TABLE) rows=4
id int PK auto_increment, field varchar(250), value varchar(5000)

## sms_apis (BASE TABLE) rows=5
id int PK auto_increment, provider varchar(45), api varchar(500), balance int, status int

## subscriberbalance (BASE TABLE) rows=25235
id int PK auto_increment, subscriberid int, amountdue decimal(10,2), timestamp varchar(40)

## subscriberpayments (BASE TABLE) rows=989763
id int PK auto_increment, transactiontype varchar(2), subscriberid varchar(11), agentid varchar(15), description varchar(250), dueamount decimal(10,2), paidamount decimal(10,2), discount decimal(10,2), subscriptioncharge decimal(10,2), duebalance decimal(10,2), dbafterdiscount decimal(10,2), timestamp varchar(50), sms varchar(300), sms_status varchar(500), sms_id varchar(500), mobile varchar(15), gateway int, is_app int, is_misc int, is_other_mobile int

## temp_cust (BASE TABLE) rows=320
customer_id varchar(200), serial_no varchar(100)

## upigateway_history (BASE TABLE) rows=120
id int PK auto_increment, bill_id int, client_txn_id varchar(100), url_response varchar(1000), status int, success_response varchar(2000), created_on datetime, created_by int, modified_on datetime, modified_by int, date date, failed_response varchar(1000)

## view_sub_apyment (VIEW)
```sql
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `view_sub_apyment` AS select `subscriberpayments`.`id` AS `id`,`subscriberpayments`.`subscriberid` AS `subscriberid`,`subscriberpayments`.`timestamp` AS `timestamp`,`subscriberpayments`.`dueamount` AS `dueamount`,`subscriberpayments`.`paidamount` AS `paidamount`,`subscriberpayments`.`discount` AS `discount`,`subscriberpayments`.`subscriptioncharge` AS `subscriptioncharge`,`subscriberpayments`.`duebalance` AS `duebalance`,`subscriberpayments`.`dbafterdiscount` AS `dbafterdiscount`,(((`subscriberpayments`.`dueamount` - `subscriberpayments`.`paidamount`) - `subscriberpayments`.`discount`) + `subscriberpayments`.`subscriptioncharge`) AS `actual_dbafterdiscount` from `subscriberpayments`
```

