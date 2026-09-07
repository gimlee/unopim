# 智能内容生成与多模型规格 (Magic AI Specification)

## Purpose

Magic AI 核心能力提供多平台生成式人工智能接入体系，以密文形式安全托管模型凭证，动态发现提供商模型清单，维护预置 Prompt 模板，并自动化完成商品营销文案生成、配图创作及保留格式的结构化多语言翻译。

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
