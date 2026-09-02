# 商品类目选择与 AI 分类

商品编辑页“商品类目归属”使用按层加载的级联下拉框，分别保存 PIM 标准主类目和商品级 TikTok 类目。平台映射维护页与商品主类目审核页复用同一组件。

商品级 TikTok 类目保存在 `product_platform_category_assignments`。上架按 SKU 解析类目时，先读取该表的已确认记录；没有记录时回退到 `category_mappings` 的已确认标准映射。当前 TikTok 地区共用 MY 类目树及官方类目 ID。

AI 分类入口复用“配置 → Magic AI → 平台”。系统只发送商品文本摘要及本地预筛选后的有限候选，返回 ID 必须属于候选集，结果只代入选择器，不会自动保存。

智谱配置：

- 业务分类使用“智谱 AI（通用 API）”，地址为 `https://open.bigmodel.cn/api/paas/v4`。
- “智谱 Code Plan（仅编码工具）”地址为 `https://open.bigmodel.cn/api/coding/paas/v4`，预置 `glm-5.3-flash`、`glm-5.3`、`glm-5.2`，默认 `glm-5.3-flash`。
- Code Plan 按智谱使用规则不参与商品分类；商品分类需使用通用 API Key。

API Key 由 `magic_ai_platforms.api_key` 加密保存。不要将真实 Key 写入代码、SQL、文档或前端模板。
