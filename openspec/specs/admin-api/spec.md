# 开放接口与系统集成规格 (Admin API Specification)

## Purpose

开放接口核心能力为外部系统、上下游服务及流水线组件提供符合标准规范的 RESTful API，涵盖 OAuth2 (Passport) 与 API Key 统一鉴权、细粒度 API ACL 权限体系、标准目录实体 CRUD、受保护二进制流媒体资产下载、AI 提示词模板与违禁词库跨系统同步、上架履历与异常追踪上报、图片多语言翻译查询，以及开箱即用的系统接口初始化指令。

## Requirements

### Requirement: OAuth2 与 API Key 统一鉴权体系
系统 SHALL 支持基于 Laravel Passport 的 OAuth2 协议（密码凭证模式与客户端凭证模式）及个人 API Key 对 REST 接口请求进行安全鉴权。

#### Scenario: 客户端凭证模式获取 Access Token
- **当 (WHEN)** 外部系统向 `/oauth/token` 端点提交合法的 `client_id` 与 `client_secret` 时
- **则 (THEN)** 系统 SHALL 签发包含约定 Scope 作用域的 Bearer Access Token，用于后续接口调用鉴权

#### Scenario: 个人 API Key 请求鉴权
- **当 (WHEN)** 外部微服务在 HTTP 请求头中携带合法的 `Authorization: Bearer {api_key}` 时
- **则 (THEN)** 系统 SHALL 校验 `api_keys` 表记录，匹配关联的角色权限并放行接口调用

### Requirement: 标准化商品目录 RESTful 端点
系统 SHALL 针对商品、类目、属性、属性族、渠道、语言及货币等核心实体提供支持分页、条件过滤与关联预加载的 REST 接口。

#### Scenario: 分页检索商品列表
- **当 (WHEN)** 授权客户端附带筛选参数请求 `GET /api/v1/rest/products` 时
- **则 (THEN)** 系统 SHALL 返回符合规范的分页商品列表，包含解析后的多语言属性值及相关关联关系数据

### Requirement: 受鉴权保护的流式媒体文件下载
系统 SHALL 提供受保护的二进制流传输端点用于下载商品图片与多媒体文件，避免直接向外暴露底层存储物理路径。

#### Scenario: 安全流式下载商品图片
- **当 (WHEN)** 具备权限的客户端请求 `GET /api/v1/rest/media-files/download` 并指定相对路径时
- **则 (THEN)** 系统 SHALL 以二进制流式响应传输文件，附带正确的 `Content-Type` 与 HTTP 缓存响应头，并在文件不存在时返回 `404`

### Requirement: 幂等式系统接口集成初始化
系统 SHALL 提供专用 Artisan 命令，自动化且幂等地初始化外部集成系统所需的 API 用户、OAuth 客户端与 API Key，避免干扰常规管理员账户。

#### Scenario: 执行集成准备初始化指令
- **当 (WHEN)** 在命令行执行 `php artisan unopim:integration:pim-provision` 时
- **则 (THEN)** 系统 SHALL 读取 `.env` 配置，幂等地创建或更新专用的集成管理员、OAuth 客户端及 API Key

### Requirement: 内容合规违禁词库与商品描述优化 REST 接口
系统 SHALL 提供受 `api-acl` 保护的端点用于查询与批量同步违禁词库，并允许通过 API 触发指定 SKU 的描述优化与违禁词清理。

#### Scenario: 批量同步违禁词库
- **当 (WHEN)** 外部系统向 `POST /api/v1/rest/content-policy/forbidden-words/sync` 提交违禁词列表载荷时
- **则 (THEN)** 系统 SHALL 在 `content_policy_forbidden_words` 中执行增量更新与去重，保持活跃词库与外部风控中台一致

#### Scenario: API 触发商品描述违禁词扫描与重写
- **当 (WHEN)** 授权系统向 `POST /api/v1/rest/content-policy/products/{sku}/optimize-description` 发起优化请求时
- **则 (THEN)** 系统 SHALL 扫描违禁词、调度对应语言的优化重写，并返回优化后文本与审计修订记录

### Requirement: AI 系统提示词模板同步 REST 接口
系统 SHALL 提供专用 REST 接口用于在外部运营系统与 UnoPim 之间批量同步和拉取特定用途（`purpose`）的 AI 系统提示词模板。

#### Scenario: 同步指定用途的系统提示词
- **当 (WHEN)** 客户端向 `POST /api/v1/rest/magic-ai/system-prompts/sync` 提交包含 `purpose`、`title`、`tone`、`max_tokens`、`temperature` 及 `is_enabled` 的模板数据时
- **则 (THEN)** 系统 SHALL 幂等写入 `magic_ai_system_prompts` 表，并在启用指定模板时自动维持同用途单一启用的互斥规则

### Requirement: 商品上架履历与异常追踪上报 REST 接口
系统 SHALL 暴露标准的接收端点，供外部 TikTok Shop 自动化执行器回传上架历史结果快照与中途发生的关键异常事件。

#### Scenario: 接收并持久化上架历史快照
- **当 (WHEN)** 外部调度器向 `POST /api/v1/rest/listing-history/products/{sku}` 提交包含 `attempt_id`、`region`、`status` 及草稿链接的载荷时
- **则 (THEN)** 系统 SHALL 在 `product_listing_histories` 中创建或更新执行记录，便于管理端可视化追踪

#### Scenario: 接收并持久化上架异常事件
- **当 (WHEN)** 执行流程向 `POST /api/v1/rest/listing-exceptions/products/{sku}` 提交包含错误码、堆栈及阻塞级别的事件时
- **则 (THEN)** 系统 SHALL 将其记录至 `product_listing_exceptions`，并触发内部上架异常通知机制

### Requirement: 商品图片翻译查询 REST 接口
系统 SHALL 提供结构化的 REST 端点供上架执行器或第三方前台查询商品已生成的多语言译图资产。

#### Scenario: 查询商品全部多语言译图
- **当 (WHEN)** 具备权限的客户端调用 `GET /api/v1/rest/products/{sku}/image-translations` 时
- **则 (THEN)** 系统 SHALL 返回按地区、语种、图片类型（主图/画廊）索引的已翻译图片 URL 列表及本地存储路径

