const URL = process.env.KV_REST_API_URL;
const TOKEN = process.env.KV_REST_API_TOKEN;
const PASSWORD = '1314';

async function kvGet(key) {
  const r = await fetch(`${URL}/get/${key}`, {
    headers: { Authorization: `Bearer ${TOKEN}` }
  });
  const d = await r.json();
  return d.result ? JSON.parse(d.result) : null;
}

async function kvSet(key, value) {
  await fetch(`${URL}/set/${key}`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${TOKEN}` },
    body: JSON.stringify(value)
  });
}

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.status(200).end();

  const action = req.query.action;
  const body = req.body || {};

  try {
    switch (action) {
      case 'get_messages':
        return res.json(await kvGet('messages') || []);

      case 'add_message': {
        const { author, content } = body;
        if (!author || !content) return res.json({ error: '姓名和内容不能为空' });
        const msgs = await kvGet('messages') || [];
        const msg = { id: Date.now(), author: author.trim(), content: content.trim(), time: new Date().toLocaleString('zh-CN') };
        msgs.push(msg);
        await kvSet('messages', msgs);
        return res.json({ success: true, id: msg.id });
      }

      case 'delete_message': {
        if (body.password !== PASSWORD) return res.json({ error: '密码错误' });
        let msgs = await kvGet('messages') || [];
        msgs = msgs.filter(m => m.id !== body.id);
        await kvSet('messages', msgs);
        return res.json({ success: true });
      }

      case 'get_bucket':
        return res.json(await kvGet('bucket_list') || {});

      case 'toggle_bucket': {
        const data = await kvGet('bucket_list') || {};
        data[body.index] = !!body.checked;
        await kvSet('bucket_list', data);
        return res.json({ success: true });
      }

      case 'get_photos':
        return res.json(await kvGet('photos') || []);

      case 'upload_photo': {
        if (body.password !== PASSWORD) return res.json({ error: '密码错误' });
        const photos = await kvGet('photos') || [];
        const photo = { id: Date.now(), data: body.data };
        photos.push(photo);
        await kvSet('photos', photos);
        return res.json({ success: true, id: photo.id });
      }

      case 'delete_photo': {
        if (body.password !== PASSWORD) return res.json({ error: '密码错误' });
        let photos = await kvGet('photos') || [];
        photos = photos.filter(p => p.id !== body.id);
        await kvSet('photos', photos);
        return res.json({ success: true });
      }

      default:
        return res.json({ error: '未知操作' });
    }
  } catch (err) {
    return res.status(500).json({ error: err.message });
  }
}
