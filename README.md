# 短剧资源管理系统

这是一个基于PHP的资源管理系统，提供资源管理、分享和转存功能。系统集成了夸克网盘API，支持资源的自动转存和分享，后台很简陋有能力的自己优化。

## 功能特点

- 资源管理：支持添加、编辑、删除和搜索资源
- 数据导出：支持TXT和CSV格式的数据导出
- 夸克网盘集成：支持自动转存和分享功能
- 响应式设计：支持PC端和移动端访问
- 快速添加：支持从分享文本快速解析并添加资源
- 多网盘支持：支持百度网盘、迅雷网盘、夸克网盘、UC网盘

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
```json
{
    "success": true/false,
    "data": null,
    "message": "操作结果说明"
}
```

#### 直接链接请求示例：
```
# GET方式添加资源
https://你的域名/apiadd.php?authorization=API密钥&title=视频标题&cover=https://pan.quark.cn/s/3f5c2c7c8d9e&episodes=20

# 返回结果示例
{
    "success": true,
    "data": null,
    "message": "数据添加成功"
}
```

### 3. 夸克网盘API (quark.php)

#### 资源转存
- 请求方式：GET
- 参数：
  - action: "save"
  - url: 分享链接
  - folder: 目标文件夹ID（可选）
  - title: 自定义标题（可选）
- 返回格式：
```json
{
    "code": 0,
    "msg": "转存结果",
    "data": {
        "taskId": "任务ID",
        "newFileIds": [...],
        "shareLink": "新的分享链接"
    }
}
```

#### 直接链接请求示例：
```
# 转存文件
https://你的域名/quark.php?action=save&url=https://pan.quark.cn/s/3f5c2c7c8d9e&title=自定义标题

# 查询任务状态
https://你的域名/quark.php?action=status&taskId=12345678

# 转存成功返回示例
{
    "code": 0,
    "msg": "转存成功",
    "data": {
        "taskId": "12345678",
        "newFileIds": ["fid1", "fid2"],
        "shareLink": "https://pan.quark.cn/s/newsharelink"
    }
}

# 任务状态返回示例
{
    "code": 0,
    "msg": "获取任务状态成功",
    "data": {
        "status": 2,
        "progress": 100,
        "message": "任务完成"
    }
}
```

注意：任务状态码说明
- status = 1: 任务进行中
- status = 2: 任务完成
- status = 3: 任务失败

### 4. 数据导出API (export.php)
- 支持格式：TXT、CSV
- 导出内容：标题、链接、更新时间
- 使用方式：/export.php?format=txt 或 /export.php?format=csv

## 前端功能

### 1. 首页功能 (index.php)
- 资源列表展示
- 按网盘类型筛选（百度、迅雷、夸克、UC）
- 搜索功能
- 分页浏览
- 数据导出
- 响应式设计

### 2. 管理后台 (manage.php)
- 资源管理
  - 添加资源：支持手动添加和快速添加
  - 编辑资源：修改标题、链接和集数
  - 删除资源：支持单条删除
  - 搜索功能：支持标题搜索
- 数据导出功能

## 安装说明

1. 环境要求
   - PHP 7.0+
   - SQLite3
   - PHP PDO扩展
   - PHP CURL扩展

2. 安装步骤
   ```bash
   # 1. 复制项目文件到网站根目录
   
   # 2. 确保以下目录可写
   chmod 777 ./ziyuan.db
   
   # 3. 修改配置文件
   # 编辑 config.php，设置管理员密码和夸克网盘Cookie
   ```

3. 配置说明
   - ADMIN_PASSWORD：管理后台密码
   - QUARK_COOKIE：夸克网盘Cookie
   - API_KEY：API访问密钥

## 技术栈

- 后端：PHP 7.0+
- 数据库：SQLite3
- 前端：HTML5 + CSS3 + JavaScript
- API：RESTful风格

## 注意事项

1. Cookie有效期
   - 夸克网盘Cookie有效期有限，需要定期更新
   - Cookie失效会导致转存功能不可用

2. 数据备份
   - 建议定期备份SQLite数据库文件
   - 可以使用导出功能备份数据

3. 使用限制
   - API调用需要验证authorization参数
   - 管理后台有登录限制
   - 文件转存可能受网盘限制

## 联系方式

如有问题请提交Issue或联系管理员。 #   z i y u a n  
 