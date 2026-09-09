# 变更提案：跨境电商工作台、1688采集、内容风控与AI优化体系 (Proposal)

## 背景与痛点 (Context & Problem)

UnoPim 作为现代化开源 PIM 系统，基础版本具备完善的 EAV 属性、可配置变体、数字护照及外汇同步能力。然而在面向以 1688 供应链货源为源头、向 TikTok Shop 等多国外部平台跨境分发商品的高频实际业务场景中，面临以下瓶颈：
1. **货源平台数据采纳断层**：人工搬运 1688 商品信息成本极高且容易出错，亟需自动化的详情提取与任务中心；
2. **多平台合规风险高**：货源文案中充斥着“工厂直销”、“一手货源”、“天猫同款”等违禁及平台词汇，直接上架极易导致封禁；
3. **多语言与跨国上架效率低**：缺乏针对多地区的并发调度、上架异常生命周期治理、上架履历追溯及商品图多语言本地化能力；
4. **AI 提示词用途耦合**：原 Magic AI 系统提示词缺乏业务场景（分类/名称/描述）隔离，无法保证特定业务场景的专属执行逻辑。

## 目标 (Goals)

1. **引入 1688 商品采集工作台与任务中心**：支持链接校验、异步抓取、任务实时日志、失败重试及本地媒体清理；
2. **构建全链路内容合规与违禁词中枢**：支持违禁词库归一化维护、高效正则扫描、AI 上下文重写与确定性离线脱敏容灾，提供不可变修订审计版本库；
3. **增强商品工作台与上架生命周期**：支持 TikTok Shop 多地区并发上架调度、实时停止、履历抽屉展示与上架异常闭环处置；
4. **扩展 Magic AI 用途隔离与图文处理**：支持 `purpose` 隔离与唯一启用、商品名称 AI 提炼、自然段描述质量规则校验与商品图片多语言翻译；
5. **完善 RESTful API 集成通道**：提供违禁词、提示词、上架履历与异常的外部同步接口及细粒度 ACL 控制。

## 涉及范围 (Scope)

- 核心数据表：`content_policy_forbidden_words`、`product_content_revisions`、`product_listing_exceptions`、`product_listing_histories`、`product_image_translations`、`exchange_rate_settings`
- 服务与引擎：`ProductContentPolicyService`、`Collection1688Controller`、`ProductImageTranslationController` 等
- 规范规格：`catalog-product`、`magic-ai`、`admin-api`、`category-taxonomy`、`exchange-rates`，新增 `product-collection` 与 `content-policy`
