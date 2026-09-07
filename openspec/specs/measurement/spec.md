# 物理度量与单位管理规格 (Measurement Specification)

## Purpose

物理度量核心能力统一管理物理计量族（如长度、重量、体积、面积等）、基准计量单位、换算系数与复合型度量属性，支持商品属性将具体数值与标准化度量单位进行强关联绑定与存储。

## Requirements

### Requirement: 计量族、度量单位与换算系数
系统 SHALL 支持定义计量族及各计量族下的具体度量单位，维护多语言显示标签，并以基准单位为锚点定义精准的换算公式。

#### Scenario: 创建计量族及其派生单位
- **当 (WHEN)** 管理员创建 `重量` 计量族，指定 `千克` 为基准单位，并添加 `克` 和 `磅` 作为派生单位时
- **则 (THEN)** 系统 SHALL 将定义保存至 `measurement_families` 和 `measurement_units`，并在 `measurement_unit_conversions` 中记录换算系数

#### Scenario: 度量单位自动换算
- **当 (WHEN)** 系统需要将 `磅` 换算为基准单位 `千克` 时
- **则 (THEN)** 系统 SHALL 基于预设的换算系数精准折算并输出目标单位数值

### Requirement: 复合型度量属性值存储
系统 SHALL 支持度量属性类型，并在商品属性 JSON 结构中将数量与度量单位代码成对封装存储。

#### Scenario: 保存商品度量属性值
- **当 (WHEN)** 保存商品度量属性 `item_weight`，输入数值 `2.5` 且选择单位 `kg` 时
- **则 (THEN)** 系统 SHALL 校验单位是否属于指定计量族，并以 `{"amount": 2.5, "unit": "kg"}` 的结构持久化在商品属性载荷中
