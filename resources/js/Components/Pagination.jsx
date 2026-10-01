import { Link } from "@inertiajs/react";
import {trans} from '@/Utils/i18n';

function decodeLabel(label) {
  return label.replace(/&laquo;/g, '\u00AB').replace(/&raquo;/g, '\u00BB');
}

export default function Pagination({ links, label }) {
  if (!links || links.length <= 3) return null;

  return (
    <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
      <div>
        <div>
          <nav className="relative z-0 inline-flex flex-wrap rounded-md shadow-sm -space-x-px" aria-label={label || trans('common.pagination')}>
            {links.map((link, index) => link.url ? (
              <Link
                preserveScroll
                preserveState
                key={index}
                href={link.url}
                aria-current={link.active ? 'page' : undefined}
                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-colors duration-200 ${
                  link.active
                    ? 'bg-blue-600 text-white border-blue-600'
                    : 'bg-white text-gray-700 border-gray-300 hover:bg-blue-500 hover:text-white'
                }`}
              >
                <div>{decodeLabel(link.label)}</div>
              </Link>
            ) : (
              <span
                key={index}
                aria-disabled="true"
                className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400"
              >
                {decodeLabel(link.label)}
              </span>
            ))}
          </nav>
        </div>
      </div>
    </div>
  );
}
