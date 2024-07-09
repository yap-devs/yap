import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head} from '@inertiajs/react';
import {formatBytes} from "@/Utils/formatBytes";
import {CategoryScale, Chart as ChartJS, Legend, LinearScale, LineElement, PointElement, Tooltip,} from 'chart.js';
import {Line} from "react-chartjs-2";

export default function Index({auth, chartData}) {
  const renderChart = () => {
    if (chartData.length === 0) {
      return (
        <div className="text-center text-gray-500">
          <div>Oops! It looks like there's nothing here.</div>
          <div>Once data is available, it will appear here.</div>
        </div>
      );
    }

    ChartJS.register(
      CategoryScale,
      LinearScale,
      PointElement,
      LineElement,
      Tooltip,
      Legend
    );

    const options = {
      elements: {
        line: {
          tension: 0.5
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: function (context) {
              let label = context.dataset.label || '';

              if (label) {
                label += ': ';
              }
              if (context.parsed.y !== null) {
                label += formatBytes(context.parsed.y);
              }
              return label;
            }
          }
        }
      },
      scales: {
        y: {
          ticks: {
            // This callback automatically formats the y-axis labels
            callback: function (value, index, values) {
              return formatBytes(value);
            }
          }
        }
      }
    };

    return <Line type="line" data={chartData} options={options}/>;
  }

  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Stat</h2>}
  >
    <Head title="Stat"/>

    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 text-gray-900">
            {renderChart()}
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
