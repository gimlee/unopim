# 智能内容生成与多模型规格 (Magic AI Specification)

## Purpose

Magic AI 核心能力提供多平台生成式人工智能接入体系，以密文形式安全托管模型凭证，动态发现提供商模型清单，基于细分业务用途（`purpose`）隔离并维护系统提示词模板与单启用约束，自动化完成商品名称提炼（去货源化与合规过滤）、商品描述结构化生成与质量规则校验、配图创作及保留格式的结构化多语言翻译。

## Requirements

### Requirement: 加密的多平台提供商凭证管理
系统 SHALL 在数据库层使用 AES-256 加密存储各 AI 提供商（OpenAI、Anthropic、Gemini、DeepSeek、智谱 AI、Ollama、Groq、xAI 及自定义端点）的 API Key，并在任何日志与返回中杜绝明文泄露。

#### Scenario: 注册并加密保存 AI 平台配置
- **当 (WHEN)** 管理员在后台注册配置包含 API Key 的新 AI 平台时
- **则 (THEN)** 系统 SHALL 在持久化写入 `magic_ai_platforms.api_key` 前执行强加密，且确保前端回显或日志绝不打印原始密钥

#### Scenario: 动态检测连通性与拉取模型清单
- **当 (WHEN)** 管理员在平台配置面板点击测试连接时
- **则 (THEN)** 系统 SHALL 经由安全代理请求提供商的 `/models` 接口，刷新 `magic_ai_platforms.model_list`，并在界面中呈现可选的模型下拉列表

### Requirement: 营销文案与 SEO 内容智能生成
系统 SHALL 能够结合预置系统 Prompt 与商品当前属性值，调用大语言模型快速生成符合特定营销语气的标题、卖点、详述和 SEO 元描述。

#### Scenario: 依据属性自动生成商品描述
- **当 (WHEN)** 运营编辑为指定商品触发 `MagicContentAgent` 并指定文案语气与参考属性时
- **则 (THEN)** 系统 SHALL 动态装配上下文提示词，调用选定 AI 平台并返回排版完备的营销文案供人工采纳

### Requirement: 保留 HTML 结构的批量多语言翻译
系统 SHALL 将商品多语言属性精准翻译至目标语种，同时严格保护原文本中的 HTML 标签、列表格式与占位符号不被破坏。

#### Scenario: 翻译富文本商品描述
- **当 (WHEN)** 执行针对包含 HTML 格式的商品描述英文转中文翻译时
- **则 (THEN)** `TranslationAgent` SHALL 施加结构化翻译约束，确保模型输出的译文中所有 HTML 标签、段落与样式代码均完整且顺序合规

### Requirement: 系统提示词用途（`purpose`）隔离与单一启用约束
系统 SHALL 在 `magic_ai_system_prompts` 中通过 `purpose` 字段严格隔离提示词的应用场景（`general`、`category_classification`、`product_name`、`product_description`），并保证同一业务用途下至多仅允许一个启用的提示词模板。

#### Scenario: 启用指定用途的新系统提示词
- **当 (WHEN)** 管理员保存或更新一条标记为 `is_enabled = true` 且用途为 `product_name` 的系统提示词模板时
- **则 (THEN)** 系统 SHALL 在事务中自动将数据库内所有同属于 `product_name` 用途的其他提示词置为 `is_enabled = false`，同时确保属于 `category_classification` 或其他用途的启用状态保持不受影响

#### Scenario: 依据业务场景自动加载对应用途提示词
- **当 (WHEN)** 后台执行商品名称优化任务时
- **则 (THEN)** 系统 SHALL 仅查询并应用 `purpose = 'product_name'` 且 `is_enabled = true` 的专用提示词模板

### Requirement: 商品名称 AI 提炼与去货源化规范
系统 SHALL 支持根据商品原标题、属性及名称模板调用 AI 提炼商品标题，强制执行负向词过滤（去除工厂、货源、批发等表述）、长度控制（60~120字符）与单行输出。

#### Scenario: 提炼商品标题并过滤货源与平台关键词
- **当 (WHEN)** 运营人员触发“AI商品名称”生成时
- **则 (THEN)** 系统 SHALL 动态构建提示词上下文，要求大模型挖掘商品核心用途与消费痛点，严格剔除“厂家直销”、“一手货源”、“代发”、“批发”以及第三方电商平台词汇，并输出单行 60~120 字符的标准标题

### Requirement: 商品描述 AI 结构化生成与质量规则校验
系统 SHALL 依据商品属性、当前商品内容 Locale 及描述模板生成多段落富文本描述，执行自然段 `<p>` 封装校验，并严格杜绝价格、产地、电商平台及虚假参数。

#### Scenario: 生成符合商品 Locale 的富文本描述
- **当 (WHEN)** 运营人员对商品执行描述优化且商品当前处于 `zh_CN` Locale 时
- **则 (THEN)** 系统 SHALL 调用启用中的 `product_description` 提示词模板生成简体中文描述，并校验返回内容至少包含两个使用 `<p>` 标签封装的自然段落

#### Scenario: 违规与夸大词强约束
- **当 (WHEN)** 模型生成商品描述时
- **则 (THEN)** 提示词约束 SHALL 严格禁止包含价格金额、折扣、原产国/产地、物流发货地及夸大绝对化表述，确保描述仅包含商品本身可验证的客观参数与规格

