import * as announcementService from '@/services/announcementService';
import {
  getAnnouncementSettings,
  saveAnnouncementSettings,
} from '@/services/announcementSettingsService';

function jsonResponse(data, ok = true, status = ok ? 200 : 400) {
  return {
    ok,
    status,
    json: async () => data,
  };
}

async function parseBody(options) {
  const body = options.body;
  if (!body) return { data: {}, files: [] };

  if (body instanceof FormData) {
    const data = {};
    const files = [];
    for (const [key, value] of body.entries()) {
      if (value instanceof File) {
        if (key.startsWith('attachments')) files.push(value);
      } else {
        data[key] = value;
      }
    }
    return { data, files };
  }

  if (typeof body === 'string') {
    try {
      return { data: JSON.parse(body), files: [] };
    } catch {
      return { data: {}, files: [] };
    }
  }

  return { data: body, files: [] };
}

const announcementsApiClient = {
  fetch: async (url, options = {}) => {
    const [path, queryString] = url.split('?');
    const params = new URLSearchParams(queryString || '');
    const method = (options.method || 'GET').toUpperCase();

    try {
      if (path === '/announcements' && method === 'GET') {
        const items = await announcementService.listAnnouncements({
          status: params.get('status') || undefined,
        });
        const filtered =
          params.get('status') === 'scheduled'
            ? items
            : items.filter((a) => a.status !== 'scheduled');
        return jsonResponse({ items: params.get('status') === 'scheduled' ? items : filtered });
      }

      if (path === '/announcements/settings' && method === 'GET') {
        const settings = await getAnnouncementSettings();
        return jsonResponse({ settings });
      }

      if (path === '/announcements/settings' && method === 'PUT') {
        const { data } = await parseBody(options);
        const settings = await saveAnnouncementSettings(data);
        return jsonResponse({ settings });
      }

      if (path === '/announcements/recipients/search' && method === 'GET') {
        const items = await announcementService.searchRecipients(
          params.get('category') || 'customers',
          params.get('query') || '',
          params.get('all') === '1' || params.get('all') === 'true'
        );
        return jsonResponse({ items });
      }

      if (path === '/announcements/preview' && method === 'POST') {
        const { data, files } = await parseBody(options);
        const preview = await announcementService.previewAnnouncementPayload(data, files);
        return jsonResponse({ preview });
      }

      if (path === '/announcements/process-scheduled' && method === 'POST') {
        const result = await announcementService.processScheduledAnnouncements();
        return jsonResponse(result);
      }

      if (path === '/announcements/bulk-delete' && method === 'POST') {
        const { data } = await parseBody(options);
        const result = await announcementService.bulkDeleteAnnouncements(data.ids || []);
        return jsonResponse(result);
      }

      if (path === '/announcements/templates' && method === 'GET') {
        const items = await announcementService.listAnnouncementTemplates(params.get('search') || '');
        return jsonResponse({ items });
      }

      if (path === '/announcements/templates' && method === 'POST') {
        const { data } = await parseBody(options);
        const record = await announcementService.createAnnouncementTemplate(data);
        return jsonResponse(record, true, 201);
      }

      if (path === '/announcements' && method === 'POST') {
        const { data, files } = await parseBody(options);
        const result = await announcementService.createAnnouncementFromRequest(data, files, data.createdBy);
        return jsonResponse(result, true, 201);
      }

      const sendMatch = path.match(/^\/announcements\/([^/]+)\/send$/);
      if (sendMatch && method === 'POST') {
        const results = await announcementService.sendAnnouncement(sendMatch[1]);
        return jsonResponse({ success: true, results });
      }

      const deleteMatch = path.match(/^\/announcements\/([^/]+)$/);
      if (deleteMatch && method === 'DELETE') {
        await announcementService.deleteAnnouncement(deleteMatch[1]);
        return jsonResponse({ success: true });
      }

      return jsonResponse({ error: 'Not found' }, false, 404);
    } catch (err) {
      return jsonResponse({ error: err.message || 'Request failed' }, false, 400);
    }
  },
};

export default announcementsApiClient;
export { announcementsApiClient };
