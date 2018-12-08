/**
* api接口统一管理
*/
import axios from './http'
import common from './common/index'
import login from './login/index'
import article from './article/index'
import menu from './menu/index'
import section from './section/index'
import role from './role/index'
import admin from './admin/index'
import config from './config/index'

const api = {
    common,
    login,
    article,
    menu,
    section,
    role,
    admin,
    config,
}

export default api