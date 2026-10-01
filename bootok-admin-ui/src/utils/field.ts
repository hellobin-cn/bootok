const field = {
  // 字符串的下划线格式转驼峰格式，eg：hello_world => helloWorld
  strUnderline2Hump(s: string) {
    return s.replace(/_(\w)/g, function (all, letter) {
      return letter.toUpperCase();
    });
  },

  // 字符串的驼峰格式转下划线格式，eg：helloWorld => hello_world
  strHump2Underline(s: string) {
    return s.replace(/([A-Z])/g, '_$1').toLowerCase();
  },

  // 字符串的驼峰格式转kebab格式，eg：systemConfig => system-config
  strHump2Kebab(s: string) {
    return s.replace(/([A-Z])/g, '-$1').toLowerCase();
  },

  // JSON对象的key值转换为驼峰式（递归）
  underline2hump(obj: any) {
    if (obj instanceof Array) {
      obj.forEach((v: any) => {
        this.underline2hump(v);
      });
    } else if (obj instanceof Object) {
      Object.keys(obj).forEach((key) => {
        const newKey = this.strUnderline2Hump(key);
        if (newKey !== key) {
          obj[newKey] = obj[key];
          delete obj[key];
        }
        this.underline2hump(obj[newKey]);
      });
    }
  },

  // JSON对象的key值转换为下划线格式（递归）
  hump2underline(obj: any) {
    if (obj instanceof Array) {
      obj.forEach((v: any) => {
        this.hump2underline(v);
      });
    } else if (obj instanceof Object) {
      Object.keys(obj).forEach((key) => {
        const newKey = this.strHump2Underline(key);
        if (newKey !== key) {
          obj[newKey] = obj[key];
          delete obj[key];
        }
        this.hump2underline(obj[newKey]);
      });
    }
  },
};

export default field;
