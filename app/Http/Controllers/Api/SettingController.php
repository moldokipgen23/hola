<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::getAll();

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $key => $value) {
            $group = $request->input("groups.{$key}", 'general');
            Setting::set($key, $value, $group);
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }

    public function publicSettings()
    {
        $settings = Setting::getAll();

        // Filter out sensitive settings (API keys, secrets, etc.)
        foreach ($settings as $group => &$items) {
            if (is_array($items)) {
                $items = array_filter($items, function ($key) {
                    $sensitivePatterns = ['api_key', 'secret', 'password', 'token', 'credential'];
                    foreach ($sensitivePatterns as $pattern) {
                        if (str_contains(strtolower($key), $pattern)) {
                            return false;
                        }
                    }

                    return true;
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        return response()->json(['settings' => $settings]);
    }

    public function sitemap()
    {
        $businesses = Business::active()
            ->select('slug', 'updated_at')
            ->get();

        $categories = Category::select('slug', 'updated_at')
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        $baseUrl = Setting::get('site_url', 'https://hola.app');

        $xml .= $this->urlEntry($baseUrl, date('c'));
        $xml .= $this->urlEntry($baseUrl.'/businesses', date('c'));
        $xml .= $this->urlEntry($baseUrl.'/categories', date('c'));

        foreach ($categories as $cat) {
            $xml .= $this->urlEntry($baseUrl.'/category/'.$cat->slug, $cat->updated_at ? $cat->updated_at->toAtomString() : date('c'));
        }

        foreach ($businesses as $biz) {
            $xml .= $this->urlEntry($baseUrl.'/business/'.$biz->slug, $biz->updated_at ? $biz->updated_at->toAtomString() : date('c'));
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    private function urlEntry(string $loc, string $lastmod): string
    {
        $loc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $lastmod = htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
    }
}
