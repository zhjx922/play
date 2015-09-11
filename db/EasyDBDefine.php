<?php
/**
 * DB Define
 */
define('VERSION',               '0.0.1'); //版本号
define('VERSION_LENGTH',        0x0010); //版本号长度

define('BLOCK_SIZE',            0x2000); //单个块儿大小
define('BLOCK_INIT_COUNT',      0x0005); //初始化块儿个数

define('BLOCK_NODE_ROOT',       0x0001); //根结点块
define('BLOCK_NODE_INTERNAL',   0x0002); //内部结点块
define('BLOCK_NODE_LEAF',       0x0003); //叶子结点块

define('FIELD_NAME_LENGTH',     0x0010); //字段名最大长度
define('FIELD_INT',             0x0001); //字段类型INT
define('FIELD_CHAR',            0x0002); //字段类型CHAR
define('FIELD_VARCHAR',         0x0003); //字段类型VARCHAR
define('FIELD_SIZE',            0x0014); //字段占空间大小

define('MAX_ID_LENGTH',         0x0004); //最大ID长度