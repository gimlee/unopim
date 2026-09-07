# 商品类目选择与 AI 分类

商品编辑页“商品类目归属”使用按层加载的级联下拉框，分别保存 PIM 标准主类目和商品级 TikTok 类目。平台映射维护页与商品主类目审核页复用同一组件。

商品级 TikTok 类目保存在 `product_platform_category_assignments`。上架按 SKU 解析类目时，先读取该表的已确认记录；没有记录时回退到 `category_mappings` 的已确认标准映射。当前 TikTok 地区共用 MY 类目树及官方类目 ID。

商品编辑页同时提供“规则分类”和“AI 分类”。AI 分类是默认推荐方式；规则分类使用 1688 来源类目映射、标准类目别名和 `category_classification_rules`，仅建议作为后备。两种结果分别缓存，界面会明确标记当前实际应用的结果；用户修改类目后显示为“人工分类”，不再把任一自动分类结果标为当前使用。AI 分类复用“配置 → Magic AI → 平台”，只发送商品摘要及本地预筛选后的有限候选，返回 ID 必须属于候选集。

AI 分类的系统提示词在“Magic AI → System Prompts”中维护，选择“AI 商品分类”用途并启用后立即生效；同一用途只能有一个启用项，不影响通用对话或商品描述用途的启用状态。

两种结果按商品分别缓存在 `product_category_classification_caches`。重新分类会覆盖对应方式的缓存；清除缓存不改变当前类目；点击“使用此结果”才会同时更新 PIM 标准主类目、商品级 TikTok 类目以及商品值中的分类状态、分类方法、置信度和依据。手动保存类目会把分类类型记为“手动分类”。

Products 列表只展示可配置主商品，并显示当前商品类目及分类类型（规则分类、AI 分类、人工分类或未分类）。

智谱配置：

- 业务分类使用“智谱 AI（通用 API）”，地址为 `https://open.bigmodel.cn/api/paas/v4`。
- “智谱 Code Plan（仅编码工具）”地址为 `https://open.bigmodel.cn/api/coding/paas/v4`，仅供智谱支持的编码工具使用，不进入商品 AI 分类平台列表。
- 商品分类使用智谱通用 OpenAI 兼容端点，可在 Magic AI 平台页测试指定模型是否能实际回答。GLM 5.x 思考深度支持 `low`、`high`、`max`，默认 `low`。
- Code Plan 按智谱使用规则不参与商品分类；商品分类需使用通用 API Key。

API Key 由 `magic_ai_platforms.api_key` 加密保存。不要将真实 Key 写入代码、SQL、文档或前端模板。
