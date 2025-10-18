<?php
// 数据库与接口的基础配置

return [
  // MySQL 连接信息
  'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'koishi',
    'user' => 'root',
    'password' => 'psd',
    'charset' => 'utf8mb4',
  ],

  // 简单接口鉴权密钥，应与 Koishi 配置中的 apiKey 一致
  'api_key' => 'yourkey',

  // 允许的最大查询条数
  'max_limit' => 100,
];



