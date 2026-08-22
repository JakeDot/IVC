import fs_pre from 'fs';
if (!fs_pre.existsSync('./data')) fs_pre.mkdirSync('./data');
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import fs from 'fs';
import path from 'path';

function formatParams(params) {
  if (!params) return {};
  if (typeof params === 'string') {
    try { params = JSON.parse(params); } catch(e) { params = {}; }
  }
  const res = {};
  for (const k in params) {
    const rawVal = params[k];
    res[k] = rawVal;
    if (k.startsWith(':') || k.startsWith('@') || k.startsWith('$')) {
      res[k.substring(1)] = rawVal;
    } else {
      res[':' + k] = rawVal;
      res['@' + k] = rawVal;
      res['$' + k] = rawVal;
    }
  }
  return res;
}

// MongoDB-compatible Document Store implementation in Node.js
class MongoCollectionStore {
  constructor(name, dataFilePath) {
    this.name = name;
    this.dataFilePath = dataFilePath;
    this.documents = [];
    this.load();
  }

  load() {
    try {
      if (fs.existsSync(this.dataFilePath)) {
        const raw = fs.readFileSync(this.dataFilePath, 'utf8');
        const data = JSON.parse(raw);
        if (Array.isArray(data[this.name])) {
          this.documents = data[this.name];
        }
      }
    } catch (e) {}
  }

  save() {
    try {
      let store = {};
      if (fs.existsSync(this.dataFilePath)) {
        try { store = JSON.parse(fs.readFileSync(this.dataFilePath, 'utf8')); } catch(e) {}
      }
      store[this.name] = this.documents;
      fs.writeFileSync(this.dataFilePath, JSON.stringify(store, null, 2));
    } catch (e) {}
  }

  _matchField(docVal, filterVal) {
    if (filterVal === undefined) return true;
    if (filterVal instanceof RegExp) {
      return filterVal.test(String(docVal ?? ''));
    }
    if (filterVal !== null && typeof filterVal === 'object' && !Array.isArray(filterVal)) {
      for (const op in filterVal) {
        const target = filterVal[op];
        if (op === '$eq') {
          if (typeof docVal === 'string' && typeof target === 'string') {
            if (docVal.toLowerCase() !== target.toLowerCase() && docVal !== target) return false;
          } else if (docVal != target) return false;
        } else if (op === '$ne') {
          if (docVal == target) return false;
        } else if (op === '$gt') {
          if (!(docVal > target)) return false;
        } else if (op === '$gte') {
          if (!(docVal >= target)) return false;
        } else if (op === '$lt') {
          if (!(docVal < target)) return false;
        } else if (op === '$lte') {
          if (!(docVal <= target)) return false;
        } else if (op === '$in') {
          if (!Array.isArray(target) || !target.some(t => String(t).toLowerCase() === String(docVal).toLowerCase())) return false;
        } else if (op === '$nin') {
          if (Array.isArray(target) && target.some(t => String(t).toLowerCase() === String(docVal).toLowerCase())) return false;
        } else if (op === '$regex') {
          const re = target instanceof RegExp ? target : new RegExp(target, filterVal.$options || 'i');
          if (!re.test(String(docVal ?? ''))) return false;
        } else if (op === '$exists') {
          const exists = docVal !== undefined && docVal !== null;
          if (target && !exists) return false;
          if (!target && exists) return false;
        }
      }
      return true;
    }
    if (typeof docVal === 'string' && typeof filterVal === 'string') {
      return docVal.toLowerCase() === filterVal.toLowerCase() || docVal === filterVal;
    }
    return docVal == filterVal;
  }

  _match(doc, query) {
    if (!query || Object.keys(query).length === 0) return true;
    if (query.$or && Array.isArray(query.$or)) {
      return query.$or.some(sub => this._match(doc, sub));
    }
    if (query.$and && Array.isArray(query.$and)) {
      return query.$and.every(sub => this._match(doc, sub));
    }
    for (const key in query) {
      if (key === '$or' || key === '$and') continue;
      if (!this._matchField(doc[key], query[key])) return false;
    }
    return true;
  }

  find(query = {}, options = {}) {
    let res = this.documents.filter(d => this._match(d, query));
    if (options.sort) {
      const sortKeys = Object.keys(options.sort);
      res.sort((a, b) => {
        for (const k of sortKeys) {
          const dir = options.sort[k] >= 0 ? 1 : -1;
          if (a[k] > b[k]) return dir;
          if (a[k] < b[k]) return -dir;
        }
        return 0;
      });
    }
    if (options.skip) res = res.slice(options.skip);
    if (options.limit) res = res.slice(0, options.limit);
    return res.map(d => ({ ...d }));
  }

  findOne(query = {}, options = {}) {
    const list = this.find(query, { ...options, limit: 1 });
    return list.length > 0 ? list[0] : null;
  }

  insertOne(doc) {
    const newDoc = { ...doc };
    this.documents.push(newDoc);
    this.save();
    return { acknowledged: true, insertedId: newDoc.id || newDoc._id || newDoc.nickname || newDoc.setting_key || newDoc.channel_name };
  }

  insertMany(docs) {
    for (const d of docs) {
      this.documents.push({ ...d });
    }
    this.save();
    return { acknowledged: true, insertedCount: docs.length };
  }

  updateOne(filter, update, options = {}) {
    let matched = 0;
    let modified = 0;
    for (let i = 0; i < this.documents.length; i++) {
      if (this._match(this.documents[i], filter)) {
        matched++;
        if (update.$set) {
          Object.assign(this.documents[i], update.$set);
        } else if (update.$unset) {
          for (const k in update.$unset) delete this.documents[i][k];
        } else {
          Object.assign(this.documents[i], update);
        }
        modified++;
        break;
      }
    }
    if (matched === 0 && options.upsert) {
      const newDoc = { ...filter, ...(update.$set || update) };
      this.insertOne(newDoc);
      return { acknowledged: true, matchedCount: 0, modifiedCount: 0, upsertedId: newDoc.id };
    }
    this.save();
    return { acknowledged: true, matchedCount: matched, modifiedCount: modified };
  }

  updateMany(filter, update, options = {}) {
    let matched = 0;
    let modified = 0;
    for (let i = 0; i < this.documents.length; i++) {
      if (this._match(this.documents[i], filter)) {
        matched++;
        if (update.$set) {
          Object.assign(this.documents[i], update.$set);
        } else if (update.$unset) {
          for (const k in update.$unset) delete this.documents[i][k];
        } else {
          Object.assign(this.documents[i], update);
        }
        modified++;
      }
    }
    this.save();
    return { acknowledged: true, matchedCount: matched, modifiedCount: modified };
  }

  deleteOne(filter) {
    const idx = this.documents.findIndex(d => this._match(d, filter));
    if (idx !== -1) {
      this.documents.splice(idx, 1);
      this.save();
      return { acknowledged: true, deletedCount: 1 };
    }
    return { acknowledged: true, deletedCount: 0 };
  }

  deleteMany(filter = {}) {
    const origLen = this.documents.length;
    this.documents = this.documents.filter(d => !this._match(d, filter));
    const deletedCount = origLen - this.documents.length;
    this.save();
    return { acknowledged: true, deletedCount };
  }

  countDocuments(filter = {}) {
    return this.documents.filter(d => this._match(d, filter)).length;
  }
}

class MongoDB {
  constructor(dataFilePath = './data/mongodb_store.json') {
    this.dataFilePath = dataFilePath;
    this.collections = new Map();
  }

  collection(name) {
    if (!this.collections.has(name)) {
      this.collections.set(name, new MongoCollectionStore(name, this.dataFilePath));
    }
    return this.collections.get(name);
  }
}

const defaultStorePath = process.env.PORT && process.env.PORT !== '3000'
  ? `./data/mongodb_store_${process.env.PORT}.json`
  : './data/mongodb_store.json';

export const mongoDb = new MongoDB(defaultStorePath);

// Expose MongoDB global methods for PHP vrzno bridge
global.mongoFind = function(collName, queryJson, optionsJson) {
  const query = queryJson ? JSON.parse(queryJson) : {};
  const options = optionsJson ? JSON.parse(optionsJson) : {};
  const res = mongoDb.collection(collName).find(query, options);
  return JSON.stringify(res);
};

global.mongoFindOne = function(collName, queryJson, optionsJson) {
  const query = queryJson ? JSON.parse(queryJson) : {};
  const options = optionsJson ? JSON.parse(optionsJson) : {};
  const res = mongoDb.collection(collName).findOne(query, options);
  return res ? JSON.stringify(res) : null;
};

global.mongoInsert = function(collName, docJson) {
  const doc = docJson ? JSON.parse(docJson) : {};
  const res = mongoDb.collection(collName).insertOne(doc);
  return JSON.stringify(res);
};

global.mongoUpdate = function(collName, filterJson, updateJson, optionsJson) {
  const filter = filterJson ? JSON.parse(filterJson) : {};
  const update = updateJson ? JSON.parse(updateJson) : {};
  const options = optionsJson ? JSON.parse(optionsJson) : {};
  const res = options.multi
    ? mongoDb.collection(collName).updateMany(filter, update, options)
    : mongoDb.collection(collName).updateOne(filter, update, options);
  return JSON.stringify(res);
};

global.mongoDelete = function(collName, filterJson, optionsJson) {
  const filter = filterJson ? JSON.parse(filterJson) : {};
  const options = optionsJson ? JSON.parse(optionsJson) : {};
  const res = options.single
    ? mongoDb.collection(collName).deleteOne(filter)
    : mongoDb.collection(collName).deleteMany(filter);
  return JSON.stringify(res);
};

global.mongoCount = function(collName, filterJson) {
  const filter = filterJson ? JSON.parse(filterJson) : {};
  return mongoDb.collection(collName).countDocuments(filter);
};

// SQL endpoints removed in favor of purely MongoDB.

let phpInstance = null;

async function copyDir(php, src, dest) {
  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = (dest === '/' ? '' : dest) + '/' + entry.name;
    if (entry.isDirectory()) {
      try { await php.mkdir(destPath); } catch(e){}
      await copyDir(php, srcPath, destPath);
    } else {
      await php.writeFile(destPath, fs.readFileSync(srcPath));
    }
  }
}

export async function getPhp() {
  if (phpInstance) return phpInstance;
  
  if (!fs.existsSync('./data')) {
    fs.mkdirSync('./data');
  }

  phpInstance = new PhpNode();
  phpInstance.addEventListener('error', e => console.error('PHP ERR:', e.detail[0]));
  phpInstance.addEventListener('output', e => { if (e.detail && e.detail[0]) process.stdout.write(e.detail[0]); });
  
  try { await phpInstance.mkdir('/src'); } catch(e){}
  await copyDir(phpInstance, './src', '/src');
  try { await phpInstance.mkdir('/tests'); } catch(e){}
  await copyDir(phpInstance, './tests', '/tests');

  // Write bootstrap
  const bootstrap = `<?php
    spl_autoload_register(function ($class) {
        $prefix = 'Fortress\\\\';
        $base_dir = '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\\\', '/', $relative_class) . '.php';
        if (file_exists($file)) require_once $file;
    });
    
    if (!function_exists('mb_strpos')) {
        function mb_strlen($string) {
            if ($string === null || $string === '') return 0;
            return count(preg_split('//u', (string)$string, -1, PREG_SPLIT_NO_EMPTY));
        }
        function mb_str_split($string, $length = 1) {
            $chars = preg_split('//u', (string)$string, -1, PREG_SPLIT_NO_EMPTY);
            if ($chars === false) return [];
            if ($length <= 1) return $chars;
            return array_map(fn($chunk) => implode('', $chunk), array_chunk($chars, $length));
        }
        function mb_substr($string, $start, $length = null) {
            $chars = preg_split('//u', (string)$string, -1, PREG_SPLIT_NO_EMPTY);
            if ($chars === false || empty($chars)) return '';
            if ($length === null) {
                return implode('', array_slice($chars, $start));
            }
            return implode('', array_slice($chars, $start, $length));
        }
        function mb_strpos($haystack, $needle, $offset = 0) {
            if ($needle === '') return $offset;
            $chars = preg_split('//u', (string)$haystack, -1, PREG_SPLIT_NO_EMPTY);
            $needleChars = preg_split('//u', (string)$needle, -1, PREG_SPLIT_NO_EMPTY);
            if ($chars === false || $needleChars === false) return false;
            $nLen = count($needleChars);
            $hLen = count($chars);
            if ($nLen === 0) return 0;
            for ($i = $offset; $i <= $hLen - $nLen; $i++) {
                if (array_slice($chars, $i, $nLen) === $needleChars) {
                    return $i;
                }
            }
            return false;
        }
    }

    Fortress\\Database\\Database::initialize();
  `;
  await phpInstance.writeFile('/bootstrap.php', bootstrap);
  await phpInstance.run(`<?php require_once '/bootstrap.php';`);
  
  return phpInstance;
}

export async function processIrcCommand(senderNick, channel, text) {
  const php = await getPhp();
  
  global.ircCommandResult = null;
  global.setIrcCommandResult = function(resJson) {
    try {
      global.ircCommandResult = typeof resJson === 'string' ? JSON.parse(resJson) : resJson;
    } catch(e) {
      global.ircCommandResult = null;
    }
  };
  
  const safeSender = JSON.stringify(senderNick || '');
  const safeChannel = JSON.stringify(channel || '');
  const safeText = JSON.stringify(text || '');

  const code = `<?php
    require_once '/bootstrap.php';
    $res = Fortress\\IRC\\IrcServices::processCommand(${safeSender}, ${safeChannel}, ${safeText});
    if ($res) {
        $js = new vrzno();
        $js->setIrcCommandResult(json_encode($res));
    }
  `;
  await php.run(code);
  return global.ircCommandResult;
}
