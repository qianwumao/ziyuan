# 短剧资源管理系统

这是一个基于PHP的资源管理系统，提供资源管理、分享和转存功能。系统集成了夸克网盘API，支持资源的自动转存和分享，后台很简陋有能力的自己优化。

## API接口说明

### 1. 资源管理API (api.php)

#### 获取资源列表
- 请求方式：GET
- 参数：
  - param: 固定值为1
  - name: 搜索关键词
  - page: 页码，从1开始
- 返回格式：
```json
{
    "code": 200,
    "msg": "获取成功",
    "data": {
        "data": [...],
        "count": 总数,
        "page": 当前页码,
        "pageSize": 每页数量
    }
}
```
### 2. 资源添加API (apiadd.php)

#### 添加资源
- 请求方式：GET/POST
- 参数：
  - authorization: API密钥
  - title: 资源标题
  - cover: 资源链接
  - episodes: 集数（可选）
- 返回格式：
