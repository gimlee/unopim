# 数字商品护照规格 (Digital Product Passport Specification)

## Purpose

数字商品护照（DPP）核心能力响应循环经济与合规性监管要求，支持根据属性族构建分段式的数字护照模板、进行公开层与内部层字段分级、生成标准 JSON-LD 结构化数据与二维码承载体，并通过专有队列完成公开层发布。

## Requirements

### Requirement: 护照模板与分层结构管理
系统 SHALL 支持高度可配置的 DPP 模板体系，包含分段章节、多语言标题以及按功能角色和公开/内部可见层划分的属性字段。

#### Scenario: 为指定属性族配置护照模板
- **当 (WHEN)** 管理员为某属性族创建包含“材料构成”、“碳足迹”等章节与属性映射的护照模板时
- **则 (THEN)** 系统 SHALL 将模板、章节和字段定义分别持久化至 `passport_templates`、`passport_template_sections` 及 `passport_template_fields` 表中

#### Scenario: 强制隔离内部私有字段
- **当 (WHEN)** 某些护照字段被标记为内部级别而非公开级别时
- **则 (THEN)** 面向公众的护照查询接口与 JSON-LD 文档输出 SHALL 严格排除未标记为公开层的内部敏感属性

### Requirement: QR 承载体与 JSON-LD 结构化文档生成
系统 SHALL 生成符合规范的机器可读 JSON-LD 文档，并生成包含护照直达链接的可扫码 QR 码实体。

#### Scenario: 导出护照 JSON-LD 规范文档
- **当 (WHEN)** 外部消费者或监管机构请求已发布商品的 JSON-LD 护照数据时
- **则 (THEN)** 系统 SHALL 输出符合欧盟标准 `@context` 声明的商品溯源与环境合规数据载荷

#### Scenario: 渲染商品二维码承载体
- **当 (WHEN)** 操作人员访问商品的护照承载体界面时
- **则 (THEN)** 系统 SHALL 动态生成嵌入了该商品公共护照 URL 的 QR 二维码图片

### Requirement: 异步队列驱动的护照发布流程
系统 SHALL 将数字商品护照的发布操作放入 `publication` 队列异步处理，校验就绪评分并受到独立的发布权限保护。

#### Scenario: 发布商品护照至公开层
- **当 (WHEN)** 具备权限的操作员对满足就绪度的商品触发护照发布时
- **则 (THEN)** 系统 SHALL 向 `publication` 队列派发生成任务，编译快照数据，并将护照状态置为已发布
