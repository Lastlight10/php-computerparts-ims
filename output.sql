PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS "users" ("id" integer primary key autoincrement not null, "username" varchar not null, "email" varchar not null, "password" varchar not null, "last_name" varchar not null, "first_name" varchar not null, "middle_name" varchar not null, "birthdate" date not null, "created_at" datetime, "updated_at" datetime, "type" varchar check ("type" in ('Staff', 'Manager', 'Admin')) not null default 'Staff');
INSERT INTO users VALUES(7,'admin','recon21342@gmail.com','$2y$10$I1GP6z3pVEP2nCs80wvLB.YLWznGevlZ3/00byzrmFaDxzPU8nuxC','Recon','Super','User','2000-01-04 00:00:00','2025-07-24 10:15:55','2025-07-25 13:34:29','Admin');
INSERT INTO users VALUES(8,'recon123','root.ayen0810@gmail.com','$2y$10$iCnpTsgnIw8nzmDYaqN.t./3CwdmSpbFDdmmObp0YB/msMAg0rhBy','Recon','Clarenz Anthony','Lunar','2002-07-09 00:00:00','2025-07-27 10:00:00','2025-07-27 12:16:00','Manager');
INSERT INTO users VALUES(9,'staff12345','r3connect12@gmail.com','$2y$10$0iW0m/nzNYEVkP/b3Fw8s.XM8EvLzGDucq9OI6oCQQpA7Iol402zK','stafflast','staffname','staffmiddle','2025-07-13 00:00:00','2025-07-27 10:12:12','2025-07-27 10:12:12','Staff');
CREATE TABLE IF NOT EXISTS "suppliers" ("id" integer primary key autoincrement not null, "supplier_type" varchar check ("supplier_type" in ('Individual', 'Company')) not null default 'Company', "company_name" varchar, "contact_first_name" varchar, "contact_middle_name" varchar, "contact_last_name" varchar, "email" varchar, "phone_number" varchar, "address" varchar, "created_at" datetime, "updated_at" datetime);
INSERT INTO suppliers VALUES(1,'Company','CPU Corporation','First','Middle','Last','cpucorp123@email.com','09123456789','dasd','2025-07-26 12:38:13','2025-07-26 12:38:13');
CREATE TABLE IF NOT EXISTS "customers" ("id" integer primary key autoincrement not null, "customer_type" varchar check ("customer_type" in ('Individual', 'Company')) not null default 'Individual', "company_name" varchar, "contact_first_name" varchar, "contact_last_name" varchar, "email" varchar, "phone_number" varchar, "address" varchar, "created_at" datetime, "updated_at" datetime, contact_middle_name AFTER contact_first_name);
INSERT INTO customers VALUES(1,'Individual',NULL,'John','Smith','email323@email.com','123333','asd','2025-07-26 16:02:37','2025-07-26 16:02:37','Name');
CREATE TABLE IF NOT EXISTS "categories" ("id" integer primary key autoincrement not null, "name" varchar not null, "description" text, "created_at" datetime, "updated_at" datetime);
INSERT INTO categories VALUES(1,'CPU','Central Processing Unit','2025-07-26 12:37:12','2025-07-26 12:37:12');
INSERT INTO categories VALUES(2,'USB - A',replace(replace('USB - Flash Drive\r\nType A','\r',char(13)),'\n',char(10)),'2025-07-26 16:43:09','2025-07-26 16:43:09');
CREATE TABLE IF NOT EXISTS "brands" ("id" integer primary key autoincrement not null, "name" varchar not null, "website" varchar, "contact_email" varchar, "created_at" datetime, "updated_at" datetime);
INSERT INTO brands VALUES(1,'AMD','https://www.amd.com','amd@email.com','2025-07-26 12:37:01','2025-07-26 12:37:01');
INSERT INTO brands VALUES(2,'Intel','https://www.intel.com','','2025-07-27 02:21:10','2025-07-27 02:21:10');
CREATE TABLE IF NOT EXISTS "products" ("id" integer primary key autoincrement not null, "sku" varchar not null, "name" varchar not null, "description" text, "category_id" integer not null, "brand_id" integer not null, "unit_price" numeric not null, "cost_price" numeric, "current_stock" integer not null default '0', "reorder_level" integer not null default '0', "is_serialized" tinyint(1) not null default '0', "is_active" tinyint(1) not null default '1', "location_aisle" varchar, "location_bin" varchar, "created_at" datetime, "updated_at" datetime, foreign key("category_id") references "categories"("id"), foreign key("brand_id") references "brands"("id"));
INSERT INTO products VALUES(1,'PROD-6884cc1308e503.53797750','AMD Ryzen 12000','AMD CPUs',1,1,500,490,26,10,1,1,NULL,NULL,'2025-07-26 12:37:39','2025-07-27 08:00:28');
INSERT INTO products VALUES(2,'PROD-688505cd7213a6.02468621','8 GB USB FLASH','USB FLASH DRIVE',2,1,210,200,5,20,0,1,NULL,NULL,'2025-07-26 16:43:57','2025-07-27 06:32:10');
INSERT INTO products VALUES(3,'PROD-68858d448fe8b8.35721731','Intel - i5 12000','Intel Cpu',1,2,600,580,3,10,1,1,NULL,NULL,'2025-07-27 02:21:56','2025-07-27 08:15:11');
INSERT INTO products VALUES(4,'PROD-6885c945eed355.21442080','AMD Ryzen 3500','AMD Ryzen 3500 CPU',1,1,690,700,0,10,1,1,NULL,NULL,'2025-07-27 06:37:57','2025-07-27 07:44:45');
CREATE TABLE IF NOT EXISTS "sequences" ("id" integer primary key autoincrement not null, "type" varchar not null, "prefix" varchar not null, "year" integer not null, "last_number" integer not null default '0', "created_at" datetime, "updated_at" datetime);
CREATE TABLE transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_type VARCHAR NOT NULL CHECK(transaction_type IN ('Sale', 'Purchase', 'Customer Return', 'Supplier Return', 'Stock Adjustment')),
    customer_id INTEGER,
    supplier_id INTEGER,
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    invoice_bill_number VARCHAR UNIQUE, -- <<< Added UNIQUE constraint directly here
    total_amount NUMERIC NOT NULL DEFAULT '0',
    status VARCHAR NOT NULL DEFAULT 'Pending' CHECK(status IN ('Pending', 'Confirmed', 'Completed', 'Cancelled')),
    notes TEXT,
    created_by_user_id INTEGER NOT NULL,
    updated_by_user_id INTEGER,
    created_at DATETIME,
    updated_at DATETIME, updated_by INTEGER NULL, amount_received NUMERIC NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
);
INSERT INTO transactions VALUES(2,'Purchase',NULL,NULL,'2025-07-26 00:00:00','PO-20250726-0001',1500,'Completed','Buy',7,7,'2025-07-26 13:20:12','2025-07-26 14:58:22',7,NULL);
INSERT INTO transactions VALUES(4,'Purchase',NULL,NULL,'2025-07-26 00:00:00','',1000,'Completed',NULL,7,7,'2025-07-26 15:28:26','2025-07-26 15:55:55',NULL,NULL);
INSERT INTO transactions VALUES(7,'Purchase',NULL,1,'2025-07-26 00:00:00','INV-6884FA959BFE3',500,'Completed','1',7,7,'2025-07-26 15:56:05','2025-07-26 16:00:43',NULL,NULL);
INSERT INTO transactions VALUES(8,'Sale',1,NULL,'2025-07-26','INV-6884FC2A86BBF',500,'Completed','buy cpu',7,7,'2025-07-26 16:02:50','2025-07-26 16:32:49',NULL,NULL);
INSERT INTO transactions VALUES(9,'Purchase',NULL,1,'2025-07-26','INV-6884FDFBAD560',1000,'Completed','dd',7,7,'2025-07-26 16:10:35','2025-07-26 16:16:53',NULL,NULL);
INSERT INTO transactions VALUES(10,'Purchase',NULL,1,'2025-07-26','INV-688505DAB5837',840,'Completed','All flash drives are packed in one box',7,7,'2025-07-26 16:44:10','2025-07-26 16:45:02',NULL,NULL);
INSERT INTO transactions VALUES(11,'Sale',1,NULL,'2025-07-26 00:00:00','INV-6885066442187',4200,'Pending','Buy 10 usb',7,7,'2025-07-26 16:46:28','2025-07-26 16:46:42',NULL,NULL);
INSERT INTO transactions VALUES(12,'Supplier Return',NULL,1,'2025-07-26','INV-6885088488177',210,'Completed','Return USB, Wrong size (4gb)',7,7,'2025-07-26 16:55:32','2025-07-26 16:56:03',NULL,NULL);
INSERT INTO transactions VALUES(13,'Sale',1,NULL,'2025-07-26 00:00:00','INV-68851036ADC12',500,'Completed','d',7,7,'2025-07-26 17:28:22','2025-07-26 17:28:49',NULL,NULL);
INSERT INTO transactions VALUES(14,'Purchase',NULL,1,'2025-07-26 00:00:00','INV-688511BC5FBDD',5000,'Completed','10 cpus',7,7,'2025-07-26 17:34:52','2025-07-26 17:35:49',NULL,NULL);
INSERT INTO transactions VALUES(16,'Purchase',NULL,1,'2025-07-26 00:00:00','INV-6885131B7DFE7',500,'Completed','12',7,7,'2025-07-26 17:40:43','2025-07-26 17:41:21',NULL,NULL);
INSERT INTO transactions VALUES(17,'Purchase',NULL,1,'2025-07-26 00:00:00','INV-6885140E71ED1',500,'Completed','d',7,7,'2025-07-26 17:44:46','2025-07-26 17:45:55',NULL,NULL);
INSERT INTO transactions VALUES(18,'Purchase',NULL,1,'2025-07-26 00:00:00','INV-688514DD347E9',500,'Completed','ds',7,7,'2025-07-26 17:48:13','2025-07-26 17:48:58',NULL,NULL);
INSERT INTO transactions VALUES(19,'Purchase',NULL,1,'2025-07-26 00:00:00','INV-688515F9CCF8C',500,'Completed','asd',7,7,'2025-07-26 17:52:57','2025-07-26 17:53:21',NULL,NULL);
INSERT INTO transactions VALUES(20,'Purchase',NULL,1,'2025-07-26 00:00:00','INV-6885179CDB538',500,'Completed','c',7,7,'2025-07-26 17:59:56','2025-07-26 18:04:17',NULL,NULL);
INSERT INTO transactions VALUES(21,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-68859724D24FE',1800,'Completed','buy intel cpu',7,7,'2025-07-27 03:04:04','2025-07-27 03:04:37',NULL,NULL);
INSERT INTO transactions VALUES(22,'Sale',1,NULL,'2025-07-27 00:00:00','INV-688598FCDA543',1200,'Completed','customer buy cpu',7,7,'2025-07-27 03:11:56','2025-07-27 03:12:24',NULL,NULL);
INSERT INTO transactions VALUES(23,'Sale',1,NULL,'2025-07-27 00:00:00','INV-688599F96C503',210,'Completed','asd',7,7,'2025-07-27 03:16:09','2025-07-27 03:43:23',NULL,NULL);
INSERT INTO transactions VALUES(25,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885C33E54B1D',920,'Completed','''',7,7,'2025-07-27 06:12:14','2025-07-27 06:32:10',NULL,920);
INSERT INTO transactions VALUES(26,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885C952EB62F',2100,'Completed',replace(replace('new cpus\r\n','\r',char(13)),'\n',char(10)),7,7,'2025-07-27 06:38:10','2025-07-27 06:44:07',NULL,2100);
INSERT INTO transactions VALUES(27,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885CB75B87F2',690,'Pending','final',7,7,'2025-07-27 06:47:17','2025-07-27 06:47:39',NULL,NULL);
INSERT INTO transactions VALUES(28,'Sale',1,NULL,'2025-07-27 00:00:00','INV-6885CC187F5E7',700,'Completed','buy new amd cpu',7,7,'2025-07-27 06:50:00','2025-07-27 06:51:12',NULL,800);
INSERT INTO transactions VALUES(29,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885CE8192B47',1690,'Completed','buy new cpus',7,7,'2025-07-27 07:00:17','2025-07-27 07:20:55',NULL,1690);
INSERT INTO transactions VALUES(30,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885D3E97E8EA',690,'Pending','12',7,7,'2025-07-27 07:23:21','2025-07-27 07:23:40',NULL,690);
INSERT INTO transactions VALUES(31,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885D4D52FFAB',690,'Completed',replace(replace('more cpus\r\n\r\n','\r',char(13)),'\n',char(10)),7,7,'2025-07-27 07:27:17','2025-07-27 07:30:43',NULL,690);
INSERT INTO transactions VALUES(32,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885D6217AD03',690,'Completed','asd',7,7,'2025-07-27 07:32:49','2025-07-27 07:33:10',NULL,690);
INSERT INTO transactions VALUES(33,'Sale',1,NULL,'2025-07-27 00:00:00','INV-6885D705E5996',0,'Pending',replace(replace('buy 5 cpus\r\n','\r',char(13)),'\n',char(10)),7,7,'2025-07-27 07:36:37','2025-07-27 07:36:37',NULL,NULL);
INSERT INTO transactions VALUES(34,'Sale',1,NULL,'2025-07-27 00:00:00','INV-6885D72548F78',3450,'Completed','buy 5',7,7,'2025-07-27 07:37:09','2025-07-27 07:44:45',NULL,3450);
INSERT INTO transactions VALUES(35,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885D9165F26A',1000,'Completed','new',7,7,'2025-07-27 07:45:26','2025-07-27 08:00:28',NULL,1000);
INSERT INTO transactions VALUES(36,'Purchase',NULL,1,'2025-07-27 00:00:00','INV-6885DFEAAC40C',1200,'Completed','c',7,7,'2025-07-27 08:14:34','2025-07-27 08:15:11',NULL,1200);
CREATE TABLE IF NOT EXISTS "product_instances" (
            "id" integer primary key autoincrement not null,
            "product_id" integer not null,
            "serial_number" varchar not null,
            "status" varchar check (
                "status" in (
                    'In Stock',
                    'Sold',
                    'Returned - Resalable',
                    'Returned - Defective',
                    'Repairing',
                    'Scrapped',
                    'Pending Stock',
                    'Adjusted Out',
                    'Removed'
                )
            ) not null default 'In Stock',
            "purchase_transaction_item_id" integer,
            "sale_transaction_item_id" integer,
            "cost_at_receipt" numeric,
            "warranty_expires_at" datetime,
            "created_at" datetime,
            "updated_at" datetime,
            "returned_from_customer_transaction_item_id" INTEGER,
            "returned_to_supplier_transaction_item_id" INTEGER,
            "adjusted_in_transaction_item_id" INTEGER,
            "adjusted_out_transaction_item_id" INTEGER,
            "created_by_user_id" INTEGER,
            "updated_by_user_id" INTEGER,
            foreign key("product_id") references "products"("id"),
            foreign key("purchase_transaction_item_id") references "transaction_items_old"("id"),
            foreign key("sale_transaction_item_id") references "transaction_items_old"("id"),
            foreign key("returned_from_customer_transaction_item_id") references "transaction_items_old"("id"),
            foreign key("returned_to_supplier_transaction_item_id") references "transaction_items_old"("id"),
            foreign key("adjusted_in_transaction_item_id") references "transaction_items_old"("id"),
            foreign key("adjusted_out_transaction_item_id") references "transaction_items_old"("id")
        );
INSERT INTO product_instances VALUES(1,1,'serial1','In Stock',2,NULL,NULL,NULL,'2025-07-26 14:58:22','2025-07-26 14:58:22',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(2,1,'serial2','In Stock',2,NULL,NULL,NULL,'2025-07-26 14:58:22','2025-07-26 14:58:22',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(3,1,'serial3','In Stock',2,NULL,NULL,NULL,'2025-07-26 14:58:22','2025-07-26 14:58:22',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(4,1,'man1','In Stock',4,NULL,NULL,NULL,'2025-07-26 15:28:58','2025-07-26 15:55:55',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(5,1,'man2','In Stock',4,NULL,NULL,NULL,'2025-07-26 15:28:58','2025-07-26 15:55:55',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(6,1,'wawa1','Sold',7,13,NULL,NULL,'2025-07-26 16:00:43','2025-07-26 17:28:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(7,1,'090-120','Sold',8,9,'',NULL,'2025-07-26 16:16:53','2025-07-26 16:32:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(8,1,'119-112','In Stock',8,NULL,500,NULL,'2025-07-26 16:16:53','2025-07-26 16:16:53',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(9,1,'abcde1','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(10,1,'abcde2','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(11,1,'abcde3','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(12,1,'abcde4','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(13,1,'abcde5','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(14,1,'abcde6','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(15,1,'ancde7','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(16,1,'abcde8','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(17,1,'ancde9','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(18,1,'abcde10','In Stock',14,NULL,'',NULL,'2025-07-26 17:35:49','2025-07-26 17:35:49',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(19,1,'new item1','In Stock',16,NULL,0,NULL,'2025-07-26 17:41:21','2025-07-26 17:41:21',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(20,1,'wal1','In Stock',17,NULL,NULL,NULL,'2025-07-26 17:45:55','2025-07-26 17:45:55',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(21,1,'Serial$1','In Stock',18,NULL,NULL,NULL,'2025-07-26 17:48:58','2025-07-26 17:48:58',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(22,1,'Buy1','In Stock',19,NULL,510,NULL,'2025-07-26 17:53:21','2025-07-26 17:53:21',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(23,1,'AMD-CPU-0001','In Stock',20,NULL,501,NULL,'2025-07-26 18:04:17','2025-07-26 18:04:17',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(24,3,'intel 1','Sold',21,22,1800,'2027-07-28','2025-07-27 03:04:37','2025-07-27 08:48:53',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(25,3,'intel 2','Sold',21,22,1800,'2028-07-07','2025-07-27 03:04:38','2025-07-27 08:49:51',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(26,3,'intel 3','In Stock',21,NULL,1800,'2026-07-27','2025-07-27 03:04:38','2025-07-27 08:51:37',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(27,1,'AMD ADS2','In Stock',26,NULL,NULL,NULL,'2025-07-27 06:32:10','2025-07-27 06:32:10',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(28,4,'NEW 1','Sold',28,30,NULL,NULL,'2025-07-27 06:44:07','2025-07-27 06:51:12',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(29,4,'NEW 2','Sold',28,36,NULL,NULL,'2025-07-27 06:44:07','2025-07-27 07:44:45',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(30,4,'NEW 3','Sold',28,36,NULL,NULL,'2025-07-27 06:44:07','2025-07-27 07:44:45',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(31,1,'AMD111','In Stock',31,NULL,500,NULL,'2025-07-27 07:20:55','2025-07-27 07:20:55',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(32,1,'AMD222','In Stock',31,NULL,500,NULL,'2025-07-27 07:20:55','2025-07-27 07:20:55',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(33,4,'AMD333','Sold',32,36,690,NULL,'2025-07-27 07:20:55','2025-07-27 07:44:45',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(34,4,'one_item','Pending Stock',33,NULL,690,NULL,'2025-07-27 07:23:40','2025-07-27 07:23:40',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(35,4,'MORE1','Sold',34,36,690,NULL,'2025-07-27 07:30:30','2025-07-27 07:44:45',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(36,4,'MORE2','Sold',35,36,690,NULL,'2025-07-27 07:33:10','2025-07-27 07:44:45',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(37,1,'More12','In Stock',37,NULL,500,NULL,'2025-07-27 08:00:28','2025-07-27 08:00:28',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(38,1,'More11','In Stock',37,NULL,500,NULL,'2025-07-27 08:00:28','2025-07-27 08:00:28',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(39,3,'int 1','In Stock',38,NULL,600,'2025-03-22','2025-07-27 08:15:11','2025-07-27 08:43:26',NULL,NULL,NULL,NULL,7,7);
INSERT INTO product_instances VALUES(40,3,'int 2','In Stock',38,NULL,600,'2026-07-25','2025-07-27 08:15:11','2025-07-27 08:43:53',NULL,NULL,NULL,NULL,7,7);
CREATE TABLE transaction_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    transaction_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price_at_transaction NUMERIC NOT NULL,
    line_total NUMERIC NOT NULL,
    is_returned_item TINYINT(1) NOT NULL DEFAULT '0',
    remarks TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    created_by_user_id INTEGER,
    updated_by_user_id INTEGER,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id), -- <<< This is the corrected line!
    FOREIGN KEY (product_id) REFERENCES products(id)
);
INSERT INTO transaction_items VALUES(2,2,1,3,500,1500,0,NULL,'2025-07-26 13:20:24','2025-07-26 13:20:24',1,1);
INSERT INTO transaction_items VALUES(4,4,1,2,500,1000,0,NULL,'2025-07-26 15:28:48','2025-07-26 15:28:48',1,1);
INSERT INTO transaction_items VALUES(7,7,1,1,500,500,0,NULL,'2025-07-26 15:56:19','2025-07-26 15:56:19',1,1);
INSERT INTO transaction_items VALUES(8,9,1,2,500,1000,0,NULL,'2025-07-26 16:10:57','2025-07-26 16:16:53',1,7);
INSERT INTO transaction_items VALUES(9,8,1,1,'',500,0,NULL,'2025-07-26 16:23:13','2025-07-26 16:32:49',1,7);
INSERT INTO transaction_items VALUES(10,10,2,4,'',840,0,NULL,'2025-07-26 16:44:33','2025-07-26 16:45:02',1,7);
INSERT INTO transaction_items VALUES(11,11,2,20,210,4200,0,NULL,'2025-07-26 16:46:42','2025-07-26 16:46:42',1,1);
INSERT INTO transaction_items VALUES(12,12,2,1,'',210,0,NULL,'2025-07-26 16:55:47','2025-07-26 16:56:03',1,7);
INSERT INTO transaction_items VALUES(13,13,1,1,'',500,0,NULL,'2025-07-26 17:28:32','2025-07-26 17:28:49',1,7);
INSERT INTO transaction_items VALUES(14,14,1,10,'',5000,0,NULL,'2025-07-26 17:35:06','2025-07-26 17:35:49',1,7);
INSERT INTO transaction_items VALUES(16,16,1,1,0,500,0,NULL,'2025-07-26 17:41:03','2025-07-26 17:41:21',1,7);
INSERT INTO transaction_items VALUES(17,17,1,1,'',500,0,NULL,'2025-07-26 17:44:56','2025-07-26 17:45:09',1,7);
INSERT INTO transaction_items VALUES(18,18,1,1,100,500,0,NULL,'2025-07-26 17:48:24','2025-07-26 17:48:58',1,7);
INSERT INTO transaction_items VALUES(19,19,1,1,510,500,0,NULL,'2025-07-26 17:53:05','2025-07-26 17:53:21',1,7);
INSERT INTO transaction_items VALUES(20,20,1,1,501,500,0,NULL,'2025-07-26 18:00:12','2025-07-26 18:04:17',1,7);
INSERT INTO transaction_items VALUES(21,21,3,3,1800,1800,0,NULL,'2025-07-27 03:04:16','2025-07-27 03:04:37',1,7);
INSERT INTO transaction_items VALUES(22,22,3,2,1000,1200,0,NULL,'2025-07-27 03:12:15','2025-07-27 03:12:24',1,7);
INSERT INTO transaction_items VALUES(23,23,2,1,210,210,0,NULL,'2025-07-27 03:16:20','2025-07-27 03:16:20',1,1);
INSERT INTO transaction_items VALUES(26,25,1,1,500,500,0,NULL,'2025-07-27 06:12:45','2025-07-27 06:32:10',1,7);
INSERT INTO transaction_items VALUES(27,25,2,2,210,420,0,NULL,'2025-07-27 06:12:54','2025-07-27 06:32:10',1,7);
INSERT INTO transaction_items VALUES(28,26,4,3,700,2100,0,NULL,'2025-07-27 06:38:26','2025-07-27 06:44:07',1,7);
INSERT INTO transaction_items VALUES(29,27,4,1,690,690,0,NULL,'2025-07-27 06:47:39','2025-07-27 06:47:39',1,1);
INSERT INTO transaction_items VALUES(30,28,4,1,700,700,0,NULL,'2025-07-27 06:50:18','2025-07-27 06:51:12',1,7);
INSERT INTO transaction_items VALUES(31,29,1,2,500,1000,0,NULL,'2025-07-27 07:00:33','2025-07-27 07:20:55',1,7);
INSERT INTO transaction_items VALUES(32,29,4,1,690,690,0,NULL,'2025-07-27 07:00:39','2025-07-27 07:20:55',1,7);
INSERT INTO transaction_items VALUES(33,30,4,1,690,690,0,NULL,'2025-07-27 07:23:31','2025-07-27 07:23:40',1,7);
INSERT INTO transaction_items VALUES(34,31,4,1,690,690,0,NULL,'2025-07-27 07:27:32','2025-07-27 07:30:30',1,7);
INSERT INTO transaction_items VALUES(35,32,4,1,690,690,0,NULL,'2025-07-27 07:33:01','2025-07-27 07:33:10',1,7);
INSERT INTO transaction_items VALUES(36,34,4,5,690,3450,0,NULL,'2025-07-27 07:37:48','2025-07-27 07:44:45',1,7);
INSERT INTO transaction_items VALUES(37,35,1,2,500,1000,0,NULL,'2025-07-27 07:45:54','2025-07-27 08:00:28',1,7);
INSERT INTO transaction_items VALUES(38,36,3,2,600,1200,0,NULL,'2025-07-27 08:14:55','2025-07-27 08:15:11',1,7);
DELETE FROM sqlite_sequence;
INSERT INTO sqlite_sequence VALUES('users',9);
INSERT INTO sqlite_sequence VALUES('brands',2);
INSERT INTO sqlite_sequence VALUES('categories',2);
INSERT INTO sqlite_sequence VALUES('products',4);
INSERT INTO sqlite_sequence VALUES('suppliers',1);
INSERT INTO sqlite_sequence VALUES('transactions',36);
INSERT INTO sqlite_sequence VALUES('product_instances',40);
INSERT INTO sqlite_sequence VALUES('customers',1);
INSERT INTO sqlite_sequence VALUES('transaction_items',38);
CREATE UNIQUE INDEX "categories_name_unique" on "categories" ("name");
CREATE UNIQUE INDEX "brands_name_unique" on "brands" ("name");
CREATE UNIQUE INDEX "products_sku_unique" on "products" ("sku");
CREATE UNIQUE INDEX "sequences_type_year_unique" on "sequences" ("type", "year");
CREATE UNIQUE INDEX "sequences_type_unique" on "sequences" ("type");
CREATE UNIQUE INDEX "product_instances_serial_number_unique" on "product_instances" ("serial_number");
COMMIT;
