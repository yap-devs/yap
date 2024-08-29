import { Link } from "@inertiajs/react";

export default function Pagination({ links }) {
  if (!links) return null;

  return (
    <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
      <div>
        <div>
          <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            {links.map((link, index) => (
              <Link
                preserveScroll
                key={index}
                href={link.url}
                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-colors duration-200 ${
                  link.active
                    ? 'bg-blue-600 text-white border-blue-600'
                    : 'bg-white text-gray-700 border-gray-300 hover:bg-blue-500 hover:text-white'
                }`}
              >
                <div dangerouslySetInnerHTML={{ __html: link.label }} />
              </Link>
            ))}
          </nav>
        </div>
      </div>
    </div>
  );
}
