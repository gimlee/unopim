# 属性系统规格 (Attribute System Specification)

## Purpose

属性系统核心能力通过动态属性、属性类型、验证规则、选项字典、属性组和属性族定义商品的数据结构模型，在整个商品目录中强制执行数据一致性约束与多语言/多渠道作用域规则。

## Requirements

### Requirement: 动态属性类型定义与输入校验
系统 SHALL 支持丰富的动态属性类型，涵盖文本、多行文本、价格、布尔值、单选下拉、多选下拉、日期时间、日期、单图、画廊、文件、复选框以及物理度量类型。

#### Scenario: 创建带有选项的单选下拉属性
- **当 (WHEN)** 管理员创建类型为 `select` 的属性并定义多语言选项值时
- **则 (THEN)** 系统 SHALL 在 `attributes` 表中持久化该属性，并在 `attribute_options` 及 `attribute_option_translations` 中保存对应选项与翻译数据

#### Scenario: 属性输入值格式校验
- **当 (WHEN)** 保存包含价格属性的商品数据时
- **则 (THEN)** 系统 SHALL 严格校验输入值为合法的数字格式，并拒绝非法非数值输入

### Requirement: 属性作用域隔离与唯一性约束
系统 SHALL 强制执行基于渠道与基于语言的作用域规则，并支持属性值的全局唯一性校验。

#### Scenario: 按渠道和语言隔离的属性赋值
- **当 (WHEN)** 属性定义中配置了 `value_per_channel = true` 与 `value_per_locale = true` 时
- **则 (THEN)** 商品编辑器与导入引擎 SHALL 要求属性值必须归属在 `channel_locale_specific.{channel}.{locale}.{attribute_code}` 键路径下

#### Scenario: 强制执行属性值唯一性约束
- **当 (WHEN)** 属性标记为 `is_unique = true` 且保存商品时传入了已被其他商品占用的属性值时
- **则 (THEN)** 系统 SHALL 终止保存操作并返回唯一性冲突错误

### Requirement: 属性族与属性组结构管理
系统 SHALL 在属性族内通过属性组组织属性字段，用于渲染商品编辑表单区块，并定义该商品类型必须遵循的字段结构。

#### Scenario: 为属性族划分属性组与分配属性
- **当 (WHEN)** 创建属性族并为其划分自定义属性组并关联具体属性时
- **则 (THEN)** 系统 SHALL 将映射持久化至 `family_group_mappings` 表，并在商品编辑界面中按组渲染对应字段区域
