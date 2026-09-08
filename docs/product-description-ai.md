# AI 商品描述与商品 Locale

商品编辑页的 Short Description 提供“描述模板”和“AI商品描述”。生成结果使用当前商品 Locale；`zh_CN` 会强制生成简体中文，并校验至少两个自然段。Short Description 是富文本属性，段落按 `<p>` 保存，商品描述优化记录继续保留生成前后的内容。

全局默认商品 Locale 由 `.env` 的 `APP_LOCALE` 控制，当前默认值为 `zh_CN`。管理员可在“设置 → 用户 → 编辑 → Catalog Locale”设置个人默认商品 Locale，也可在商品编辑页顶部的语言下拉框临时切换。字段旁的 `ZH_CN`、`EN_US` 是内容范围标识；UI Locale 只控制后台界面语言，不控制商品内容语言。

Meta Description 是给独立前台页面和搜索引擎使用的 SEO 摘要。当前 TikTok 上架流程不使用它，因此商品编辑页隐藏该字段，但 Attribute 和历史数据仍保留。
