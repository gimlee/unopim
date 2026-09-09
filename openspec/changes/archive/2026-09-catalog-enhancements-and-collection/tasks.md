# 实施任务清单与完成记录 (Tasks)

## 1. 货源采集域 (1688 Collection)
- [x] 新增 `Collection1688Controller`，集成 1688 链接多模式正则提取与下游端点代理
- [x] 构建 `collection/1688/index.blade.php` 采集工作台与任务中心 UI
- [x] 实现采集任务异步轮询、失败重试 (`retry`)、源链接热修改 (`update`) 与任务级联彻底删除 (`destroy`)
- [x] 初始化简体中文（`zh_CN`）与人民币（`CNY`）默认配置
- [x] 优化通知条幅布局与闪烁消息队列

## 2. 内容合规与违禁词风控域 (Content Policy & Compliance)
- [x] 创建 `content_policy_forbidden_words` 数据库表及小写归一化唯一索引
- [x] 创建 `product_content_revisions` 不可变修订版本审计表
- [x] 开发 `ProductContentPolicyService` 高吞吐正则扫描引擎与 AI 重写适配器
- [x] 实现 AI 接口不可用时的安全离线脱敏兜底（`fallback` 模式）
- [x] 开发 `ForbiddenWordController`、`ForbiddenWordDataGrid` 及管理端交互界面
- [x] 在商品编辑页提供内容合规版本对比面板与敏感词高亮视图

## 3. 商品工作台与上架调度域 (Catalog Workbench & Listing)
- [x] 创建 `product_listing_exceptions` 表，捕获全生命周期上架异常并支持闭环处理
- [x] 创建 `product_listing_histories` 表，记录多地区上架尝试详情
- [x] 开发商品编辑页“上架历史”抽屉面板（时间倒序呈现最近 100 次记录）
- [x] 在商品主数据表格中新增最新上架状态徽标列与状态过滤器
- [x] 支持 TikTok Shop 多地区并发上架调度与实时停止上架 (`/listing/cancel`)
- [x] 实现一键 AI 优化流水线（分类 -> 名称 -> 描述分项联动与独立阶段选择）

## 4. 视觉与智能内容增强 (Visuals & Magic AI)
- [x] `magic_ai_system_prompts` 增加 `purpose` 业务用途字段与同用途唯一启用互斥机制
- [x] 增加商品名称优化专属提示词，支持去货源化与 60~120 字符长度控制
- [x] 强化商品描述优化提示词，支持自然段 `<p>` 校验与质量规则过滤
- [x] 创建 `product_image_translations` 表，支持多语种图片翻译生成与持久化
- [x] 开发商品图片多语言翻译面板、左右对比查看器与双击无损大图放大弹窗

## 5. RESTful API 与 API ACL (API & Integration)
- [x] 新增违禁词查询与同步 REST 接口：`GET/POST /api/v1/rest/content-policy/forbidden-words`
- [x] 新增商品描述优化 REST 接口：`POST /api/v1/rest/content-policy/products/{sku}/optimize-description`
- [x] 新增 AI 系统提示词双向同步 REST 接口：`GET/POST /api/v1/rest/magic-ai/system-prompts`
- [x] 新增上架履历与异常上报 REST 接口：`POST /api/v1/rest/listing-history/products/{sku}`、`POST /api/v1/rest/listing-exceptions/products/{sku}`
- [x] 新增商品图片翻译查询 REST 接口：`GET /api/v1/rest/products/{sku}/image-translations`
- [x] 在 `api-acl.php` 中配置所有新增接口的细粒度权限控制树
